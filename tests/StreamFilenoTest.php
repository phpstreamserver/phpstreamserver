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
        $this->assertSame(0, StreamFileno::get(STDIN));
        $this->assertSame(1, StreamFileno::get(STDOUT));
        $this->assertSame(2, StreamFileno::get(STDERR));
    }

    public function testReturnsValidDescriptorForFileStream(): void
    {
        // Arrange
        $tempPath = \sys_get_temp_dir() . '/phpss-test-' . \uniqid();
        $resource = \fopen($tempPath, 'a');

        // Assert
        try {
            $fd = StreamFileno::get($resource);

            $this->assertIsInt($fd);
            $this->assertGreaterThan(2, $fd);
        } finally {
            \fclose($resource);
            \unlink($tempPath);
        }
    }

    public function testReturnsValidDescriptorForSocketPair(): void
    {
        // Arrange
        $pair = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        // Assert
        try {
            $fd0 = StreamFileno::get($pair[0]);
            $fd1 = StreamFileno::get($pair[1]);

            $this->assertIsInt($fd0);
            $this->assertIsInt($fd1);
            $this->assertGreaterThan(2, $fd0);
            $this->assertGreaterThan(2, $fd1);
            $this->assertNotSame($fd0, $fd1);
        } finally {
            \fclose($pair[0]);
            \fclose($pair[1]);
        }
    }

    public function testReturnsNullForNonFileResource(): void
    {
        // Arrange
        $resource = \fopen('php://memory', 'r');

        // Assert
        try {
            $this->assertNull(StreamFileno::get($resource));
        } finally {
            \fclose($resource);
        }
    }

    public function testReturnsNullForNonStreamResource(): void
    {
        // Arrange
        $resource = \stream_context_create();

        // Assert
        $this->assertNull(StreamFileno::get($resource));
    }

    public function testThrowsExceptionForNonResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StreamFileno::get('string');
    }

    public function testThrowsExceptionForClosedResource(): void
    {
        $resource = \fopen('php://memory', 'r');
        \fclose($resource);

        $this->expectException(\InvalidArgumentException::class);
        StreamFileno::get($resource);
    }
}
