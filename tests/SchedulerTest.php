<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Plugin\Scheduler\Message\GetSchedulerStatusCommand;
use PHPStreamServer\Plugin\Scheduler\Status\PeriodicWorkerInfo;
use PHPStreamServer\Test\data\PHPSSTestCase;

final class SchedulerTest extends PHPSSTestCase
{
    public function testWorkersAreRegistered(): void
    {
        // Arrange
        $schedulerStatus = $this->dispatch(new GetSchedulerStatusCommand());
        $names = \array_map(array: $schedulerStatus->getPeriodicWorkers(), callback: static fn(PeriodicWorkerInfo $p) => $p->name);

        // Assert
        $this->assertSame(1, $schedulerStatus->getPeriodicTasksCount());
        $this->assertContains('Periodic Process 1', $names);
    }

    public function testPeriodicWorkerExecutes(): void
    {
        // Arrange
        $tmpFile = \sys_get_temp_dir() . '/phpss-test-9af00c2f.txt';
        \unlink($tmpFile);

        // Act
        \usleep(1500000);

        // Assert
        $this->assertFileExists($tmpFile, 'Periodic worker did not execute');
    }
}
