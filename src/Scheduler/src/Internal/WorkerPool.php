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
        /**
         * @var \WeakMap<PeriodicProcess, int>
         */
        $this->pidMap = new \WeakMap();
    }

    public function addWorker(PeriodicProcess $worker): void
    {
        $this->pool[\spl_object_id($worker)] = $worker;
    }

    public function addChild(PeriodicProcess $worker, int $pid): void
    {
        if (!isset($this->pool[\spl_object_id($worker)])) {
            throw new PHPStreamServerException('PeriodicProcess is not fount in pool');
        }

        $this->pidMap[$worker] = $pid;
    }

    public function deleteChild(PeriodicProcess $worker): void
    {
        unset($this->pidMap[$worker]);
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

    public function isWorkerRun(PeriodicProcess $worker): bool
    {
        return isset($this->pidMap[$worker]);
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
