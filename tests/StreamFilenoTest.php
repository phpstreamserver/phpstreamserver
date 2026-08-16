<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Internal\FFIBindings\StreamFileno;
use PHPUnit\Framework\TestCase;

final class StreamFilenoTest extends TestCase
{
    public function testReturnsValidDescriptorForStandardStreams(): void
    {
        // Assert
        $this->assertSame(0, StreamFileno::fileno(STDIN));
        $this->assertSame(1, StreamFileno::fileno(STDOUT));
        $this->assertSame(2, StreamFileno::fileno(STDERR));
    }

    public function testReturnsValidDescriptorForFileStream(): void
    {
        // Arrange
        $tempPath = \sys_get_temp_dir() . '/phpss-test-' . \uniqid();
        $resource = \fopen($tempPath, 'a');

        // Assert
        try {
            $fd = StreamFileno::fileno($resource);
            $this->assertIsInt($fd);
            $this->assertGreaterThan(2, $fd);
        } finally {
            \unlink($tempPath);
        }
    }

    public function testReturnsNullForNonResource(): void
    {
        // Assert
        $this->assertNull(StreamFileno::fileno('string'));
        $this->assertNull(StreamFileno::fileno(123));
    }

    public function testReturnsNullForNonFileResource(): void
    {
        // Arrange
        $resource = \fopen('php://memory', 'r');

        // Assert
        try {
            $this->assertNull(StreamFileno::fileno($resource));
        } finally {
            \fclose($resource);
        }
    }
}
