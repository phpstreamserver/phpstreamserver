<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Plugin\Scheduler\Worker\PeriodicProcess;

use function PHPStreamServer\Core\generateWorkerId;

/**
 * @internal
 */
final class WorkerPool
{
    /**
     * @var array<int, PeriodicProcess>
     */
    private array $pool = [];

    /**
     * @var \WeakMap<PeriodicProcess, int>
     */
    private \WeakMap $pidMap;

    public function __construct()
    {
        /** @var \WeakMap<PeriodicProcess, int> */
        $this->pidMap = new \WeakMap();
    }

    public function registerWorker(PeriodicProcess $worker): void
    {
        /** @psalm-suppress RedundantCondition */
        if (isset($worker->id)) {
            throw new PHPStreamServerException('Worker already registered in the pool');
        }

        $workerId = generateWorkerId();

        /**
         * Assign unique sequential id and name if not set
         * @psalm-suppress PossiblyNullFunctionCall, UndefinedThisPropertyFetch, UndefinedThisPropertyAssignment
         */
        \Closure::bind(function () use ($workerId): void {
            $this->id = $workerId;
            $this->name ??= 'periodic worker ' . $this->id;
        }, $worker, $worker)();

        $this->pool[$workerId] = $worker;
    }

    public function unregisterWorker(PeriodicProcess $worker): void
    {
        if (!isset($this->workerPool[$worker->id])) {
            throw new PHPStreamServerException('Worker not registered in the pool');
        }

        unset($this->pool[$worker->id]);
    }

    public function addChild(PeriodicProcess $worker, int $pid): void
    {
        if (!isset($this->pool[$worker->id])) {
            throw new PHPStreamServerException('Worker is not found in pool');
        }

        $this->pidMap->offsetSet($worker, $pid);
    }

    public function removeChild(PeriodicProcess $worker): void
    {
        $this->pidMap->offsetUnset($worker);
    }

    public function getWorkerByPid(int $pid): PeriodicProcess|null
    {
        foreach ($this->pidMap as $worker => $workerPid) {
            if ($pid === $workerPid) {
                return $worker;
            }
        }

        return null;
    }

    public function getPidByWorker(PeriodicProcess $worker): int|null
    {
        if ($this->pidMap->offsetExists($worker)) {
            return $this->pidMap->offsetGet($worker);
        } else {
            return null;
        }
    }

    public function isWorkerRun(PeriodicProcess $worker): bool
    {
        return $this->pidMap->offsetExists($worker);
    }

    /**
     * @return \Iterator<PeriodicProcess>
     */
    public function getWorkers(): \Iterator
    {
        return new \ArrayIterator($this->pool);
    }

    public function getWorkerCount(): int
    {
        return \count($this->pool);
    }

    public function getProcessesCount(): int
    {
        return $this->pidMap->count();
    }
}
