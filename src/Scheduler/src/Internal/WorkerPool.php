<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use PHPStreamServer\Core\Exception\PHPStreamServerException;
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
     * @var array<int, int>
     */
    private array $pids = [];

    public function __construct()
    {
    }

    public function addWorker(ScheduledWorker $worker, string|null $factoryId, \DateTimeImmutable $currentDate, \DateTimeImmutable $nextRunDate): WorkerInfo
    {
        $id = $worker->getId();
        $workerInfo = new WorkerInfo(
            id: $id,
            name: $worker->getName(),
            user: $worker->getUser(),
            group: $worker->getGroup(),
            schedule: $worker->schedule,
            status: WorkerInfo::STATUS_SCHEDULED,
            factoryId: $factoryId,
            startedAt: $currentDate,
            nextRunDateTime: $nextRunDate,
        );
        $this->workerInfosById[$id] = $workerInfo;

        return $workerInfo;
    }

    public function removeWorker(int $workerId): void
    {
        $worker = $this->getWorkerInfoById($workerId) ?? throw new PHPStreamServerException('Worker is not registered in the pool');

        if ($worker->status === WorkerInfo::STATUS_RUNNING || $worker->status === WorkerInfo::STATUS_CANCELLING) {
            $worker->status = WorkerInfo::STATUS_CANCELLING;
            return;
        }

        unset($this->workerInfosById[$worker->id]);
        unset($this->pids[$worker->id]);
    }

    public function addProcess(int $workerId, int $pid): void
    {
        $worker = $this->getWorkerInfoById($workerId) ?? throw new PHPStreamServerException('Worker is not registered in the pool');
        $worker->status = WorkerInfo::STATUS_RUNNING;
        $this->pids[$workerId] = $pid;
    }

    public function removeProcess(int $pid): void
    {
        if (null === $worker = $this->getWorkerInfoByPid($pid)) {
            return;
        }

        unset($this->pids[$worker->id]);

        if ($worker->status === WorkerInfo::STATUS_CANCELLING) {
            unset($this->workerInfosById[$worker->id]);
        } else {
            $worker->status = WorkerInfo::STATUS_SCHEDULED;
        }
    }

    public function updateNextRunDate(int $workerId, \DateTimeImmutable $nextRunDate): void
    {
        $worker = $this->getWorkerInfoById($workerId) ?? throw new PHPStreamServerException('Worker is not registered in the pool');
        $worker->nextRunDateTime = $nextRunDate;
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
