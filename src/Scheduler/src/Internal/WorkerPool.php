<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Plugin\Scheduler\Worker\PeriodicProcess;

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
        $this->pool[$worker->id] = $worker;
    }

    public function unregisterWorker(PeriodicProcess $worker): void
    {
        if (!isset($this->pool[$worker->id])) {
            throw new PHPStreamServerException('Worker is not registered in the pool');
        }

        unset($this->pool[$worker->id]);
    }

    public function addChild(PeriodicProcess $worker, int $pid): void
    {
        if (!isset($this->pool[$worker->id])) {
            throw new PHPStreamServerException('Worker is not registered in the pool');
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

    public function isWorkerRunning(PeriodicProcess $worker): bool
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

    public function getProcessCount(): int
    {
        return $this->pidMap->count();
    }
}
