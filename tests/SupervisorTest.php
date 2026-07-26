<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Command\GetProcessesCommand;
use PHPStreamServer\Core\Command\GetWorkersCommand;
use PHPStreamServer\Core\Command\ReloadServerCommand;
use PHPStreamServer\Core\Plugin\Supervisor\ProcessInfo;
use PHPStreamServer\Core\Plugin\Supervisor\WorkerInfo;
use PHPStreamServer\Test\data\PHPSSTestCase;
use PHPUnit\Framework\Attributes\Depends;

final class SupervisorTest extends PHPSSTestCase
{
    public function testWorkersAreRegistered(): void
    {
        // Arrange
        $workers = $this->dispatch(new GetWorkersCommand());
        $names = \array_map(array: $workers, callback: static fn(WorkerInfo $w) => $w->name);

        // Assert
        $this->assertCount(5, $workers);
        $this->assertContains('Worker 1', $names);
        $this->assertContains('Worker 2', $names);
        $this->assertContains('External 1', $names);
        $this->assertContains('External 2', $names);
        $this->assertContains('HTTP Server', $names);
    }

    /**
     * @return non-empty-list<int>
     */
    public function testProcessesAreSpawned(): array
    {
        // Arrange
        $processes = $this->dispatch(new GetProcessesCommand());
        $names = \array_map(array: $processes, callback: static fn(ProcessInfo $p) => $p->name);

        // Assert
        $this->assertCount(6, $processes);
        $this->assertContains('Worker 1', $names);
        $this->assertContains('Worker 2', $names);
        $this->assertContains('External 1', $names);
        $this->assertContains('External 2', $names);
        $this->assertContains('HTTP Server', $names);

        return $this->getPids($processes);
    }

    #[Depends('testProcessesAreSpawned')]
    public function testProcessIsRestartedAfterBeingKilled(array $pids): void
    {
        // Act
        \posix_kill($pids[0], SIGKILL);
        \usleep(400000);

        // Assert
        $processes = $this->dispatch(new GetProcessesCommand());

        $this->assertCount(6, $processes);
        $this->assertNotSame($pids, $this->getPids($processes));
    }

    #[Depends('testProcessIsRestartedAfterBeingKilled')]
    public function testProcessesAreReloadedByCommand(): void
    {
        // Arrange
        $processes = $this->dispatch(new GetProcessesCommand());
        $reloadablePids = [];
        $notReloadablePids = [];
        foreach ($processes as $process) {
            $process->reloadable ? $reloadablePids[] = $process->pid : $notReloadablePids[] = $process->pid;
        }
        \sort($reloadablePids, SORT_NUMERIC);
        \sort($notReloadablePids, SORT_NUMERIC);

        // Act
        $this->dispatch(new ReloadServerCommand());
        \usleep(400000);

        // Assert
        $newProcesses = $this->dispatch(new GetProcessesCommand());
        $newPids = $this->getPids($newProcesses);

        $this->assertSame($notReloadablePids, \array_values(\array_intersect($newPids, $notReloadablePids)), 'At least one non-reloadable process was reloaded');
        $this->assertEmpty(\array_intersect($newPids, $reloadablePids), 'At least one reloadable process was not reloaded');
    }

    /**
     * @param array<ProcessInfo> $processes
     * @return non-empty-list<int>
     */
    private function getPids(array $processes): array
    {
        $pids = \array_map(array: $processes, callback: static fn(ProcessInfo $p) => $p->pid);
        \sort($pids, SORT_NUMERIC);
        return $pids;
    }
}
