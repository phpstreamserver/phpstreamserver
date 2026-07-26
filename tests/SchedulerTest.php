<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Plugin\Scheduler\Command\GetWorkersCommand;
use PHPStreamServer\Plugin\Scheduler\WorkerInfo;
use PHPStreamServer\Test\data\PHPSSTestCase;

final class SchedulerTest extends PHPSSTestCase
{
    public function testWorkersAreRegistered(): void
    {
        // Arrange
        $workers = $this->dispatch(new GetWorkersCommand());
        $names = \array_map(array: $workers, callback: static fn(WorkerInfo $p) => $p->name);

        // Assert
        $this->assertCount(1, $workers);
        $this->assertContains('Scheduled worker 1', $names);
    }

    public function testPeriodicWorkerExecutes(): void
    {
        // Arrange
        $tmpFile = \sys_get_temp_dir() . '/phpss-test-9af00c2f.txt';
        \unlink($tmpFile);

        // Act
        \usleep(1500000);

        // Assert
        $this->assertFileExists($tmpFile, 'Scheduled worker did not execute');
    }
}
