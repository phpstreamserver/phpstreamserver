<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Message\GetSupervisorStatusCommand;
use PHPStreamServer\Core\Message\ReloadServerCommand;
use PHPStreamServer\Core\Plugin\Supervisor\Status\ProcessInfo;
use PHPStreamServer\Core\Plugin\Supervisor\Status\WorkerInfo;
use PHPStreamServer\Test\data\PHPSSTestCase;
use PHPUnit\Framework\Attributes\Depends;

final class SupervisorTest extends PHPSSTestCase
{
    public function testProcessesAreRegistered(): void
    {
        // Arrange
        $supervisorStatus = $this->dispatch(new GetSupervisorStatusCommand());
        $names = \array_map(array: $supervisorStatus->getWorkers(), callback: static fn(WorkerInfo $w) => $w->name);

        // Assert
        $this->assertSame(5, $supervisorStatus->getWorkersCount());
        $this->assertContains('Worker Process 1', $names);
        $this->assertContains('Worker Process 2', $names);
        $this->assertContains('External Process 1', $names);
        $this->assertContains('External Process 2', $names);
        $this->assertContains('HTTP Server', $names);
    }

    /**
     * @return non-empty-list<int>
     */
    public function testProcessesAreSpawned(): array
    {
        // Arrange
        $supervisorStatus = $this->dispatch(new GetSupervisorStatusCommand());
        $names = \array_map(array: $supervisorStatus->getProcesses(), callback: static fn(ProcessInfo $p) => $p->name);

        // Assert
        $this->assertSame(6, $supervisorStatus->getProcessesCount());
        $this->assertContains('Worker Process 1', $names);
        $this->assertContains('Worker Process 2', $names);
        $this->assertContains('External Process 1', $names);
        $this->assertContains('External Process 2', $names);
        $this->assertContains('HTTP Server', $names);

        return $this->getPids($supervisorStatus->getProcesses());
    }

    #[Depends('testProcessesAreSpawned')]
    public function testProcessIsRestartedAfterKill(array $pids): void
    {
        // Act
        \posix_kill($pids[0], SIGKILL);
        \usleep(400000);

        // Assert
        $newSupervisorStatus = $this->dispatch(new GetSupervisorStatusCommand());
        $newProcesses = $newSupervisorStatus->getProcesses();

        $this->assertCount(6, $newProcesses);
        $this->assertNotSame($pids, $this->getPids($newProcesses));
    }

    #[Depends('testProcessIsRestartedAfterKill')]
    public function testProcessesAreReloadedByCommand(): void
    {
        // Arrange
        $supervisorStatus = $this->dispatch(new GetSupervisorStatusCommand());
        $reloadablePids = [];
        $notReloadablePids = [];
        foreach ($supervisorStatus->getProcesses() as $process) {
            $process->reloadable ? $reloadablePids[] = $process->pid : $notReloadablePids[] = $process->pid;
        }
        \sort($reloadablePids, SORT_NUMERIC);
        \sort($notReloadablePids, SORT_NUMERIC);

        // Act
        $this->dispatch(new ReloadServerCommand());
        \usleep(400000);

        // Assert
        $newSupervisorStatus = $this->dispatch(new GetSupervisorStatusCommand());
        $newPids = $this->getPids($newSupervisorStatus->getProcesses());

        $this->assertSame($notReloadablePids, \array_values(\array_intersect($newPids, $notReloadablePids)), 'Not reloadable worker was reloaded');
        $this->assertEmpty(\array_intersect($newPids, $reloadablePids), 'Reloadable worker was not reloaded');
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
