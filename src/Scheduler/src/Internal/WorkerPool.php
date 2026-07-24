<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerFactory;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerInterface;
use PHPStreamServer\Plugin\Scheduler\Worker\ScheduledWorker;
use PHPStreamServer\Plugin\Scheduler\WorkerInfo;

/**
 * @internal
 */
final class WorkerPool
{
    /**
     * @var array<int, WorkerInfo>
     */
    private array $workerInfosById = [];

    /**
     * @var array<int, TriggerInterface>
     */
    private array $triggersById = [];

    /**
     * @var array<int, int>
     */
    private array $pids = [];

    public function __construct()
    {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function addWorker(ScheduledWorker $worker): WorkerInfo
    {
        $now = new \DateTimeImmutable('now');
        $trigger = TriggerFactory::create($worker->schedule, $worker->jitter);
        $nextRunDate = $trigger->getNextRunDate($now);

        if ($nextRunDate === null) {
            throw new \InvalidArgumentException('Next run date is not valid');
        }

        $workerInfo = new WorkerInfo(
            id: $worker->id,
            name: $worker->name,
            user: $worker->getUser(),
            schedule: $worker->schedule,
            status: WorkerInfo::STATUS_SCHEDULED,
            nextRunDateTime: $nextRunDate,
        );

        $this->workerInfosById[$worker->id] = $workerInfo;
        $this->triggersById[$worker->id] = $trigger;

        return $workerInfo;
    }

    public function removeWorker(int $workerId): void
    {

        if (null === $worker = $this->getWorkerInfoById($workerId)) {
            throw new PHPStreamServerException('Worker is not registered in the pool');
        }

        if ($worker->status === WorkerInfo::STATUS_RUNNING || $worker->status === WorkerInfo::STATUS_CANCEL) {
            $worker->status = WorkerInfo::STATUS_CANCEL;
            return;
        }

        unset($this->workerInfosById[$worker->id]);
        unset($this->triggersById[$worker->id]);
        unset($this->pids[$worker->id]);
    }

    public function addProcess(int $workerId, int $pid): void
    {
        if (null === $worker = $this->getWorkerInfoById($workerId)) {
            throw new PHPStreamServerException('Worker is not registered in the pool');
        }

        $worker->status = WorkerInfo::STATUS_RUNNING;
        $this->pids[$workerId] = $pid;
    }

    public function removeProcess(int $pid): void
    {
        if (null === $worker = $this->getWorkerInfoByPid($pid)) {
            return;
        }

        unset($this->pids[$worker->id]);

        if ($worker->status === WorkerInfo::STATUS_CANCEL) {
            unset($this->workerInfosById[$worker->id]);
            unset($this->triggersById[$worker->id]);
        } else {
            $worker->status = WorkerInfo::STATUS_SCHEDULED;
        }
    }

    public function calculateNextRunDate(int $workerId, \DateTimeImmutable $now): \DateTimeImmutable|null
    {
        if (null === $worker = $this->getWorkerInfoById($workerId)) {
            throw new PHPStreamServerException('Worker is not registered in the pool');
        }

        $nextRunDate = $this->triggersById[$workerId]->getNextRunDate($now);

        if ($nextRunDate !== null) {
            $worker->nextRunDateTime = $nextRunDate;
        }

        return $nextRunDate;
    }

    public function getWorkerInfoById(int $workerId): WorkerInfo|null
    {
        return $this->workerInfosById[$workerId] ?? null;
    }

    public function getWorkerInfoByPid(int $pid): WorkerInfo|null
    {
        $workerId = \array_search($pid, $this->pids, true);

        if ($workerId === false) {
            return null;
        }

        return $this->workerInfosById[$workerId] ?? null;
    }

    public function getPidById(int $workerId): int|null
    {
        return $this->pids[$workerId] ?? null;
    }

    public function isWorkerRunning(int $workerId): bool
    {
        return \array_key_exists($workerId, $this->pids);
    }

    public function hasRunningWorkers(): bool
    {
        return \count($this->pids) > 0;
    }

    /**
     * @return array<WorkerInfo>
     */
    public function getWorkerInfos(): array
    {
        return \array_values($this->workerInfosById);
    }
}
