<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Internal\ProcessMemory\ProcessMemory;
use PHPUnit\Framework\TestCase;

final class ProcessMemoryTest extends TestCase
{
    public function testMemoryUsage(): void
    {
        $this->assertGreaterThan(0, ProcessMemory::get(\posix_getpid()));
    }
}
