<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPUnit\Framework\TestCase;

use function PHPStreamServer\Core\getMemoryUsageByPid;

final class MemoryUsageByPidTest extends TestCase
{
    public function testDarwinMemoryUsage(): void
    {
        $this->assertGreaterThan(0, getMemoryUsageByPid(\posix_getpid()));
    }
}
