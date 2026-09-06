<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Internal\CloseOnExec\CloseOnExec;
use PHPStreamServer\Core\Internal\FFIBindings\StreamFileno;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;
use PHPUnit\Framework\TestCase;

#[RequiresOperatingSystem('^(Linux|Darwin|FreeBSD|OpenBSD)$')]
final class CloseOnExecTest extends TestCase
{
    private const FD_CLOEXEC = 1;

    public function testSetsCloseOnExecFlag(): void
    {
        // Arrange
        $resource = \tmpfile();
        $fd = StreamFileno::get($resource);

        $pid = \pcntl_fork();
        $this->assertNotSame(-1, $pid, 'Failed to fork child process');

        // Act
        if ($pid === 0) {
            // Forked process
            $ffi = \FFI::cdef('int fcntl(int fd, int op, ...); void _exit(int status);');
            try {
                CloseOnExec::set();
                // Exit with 0 if FD_CLOEXEC was set, otherwise exit with 1.
                $ffi->_exit(($ffi->fcntl($fd, 1) & self::FD_CLOEXEC) === self::FD_CLOEXEC ? 0 : 1);
            } catch (\Throwable) {
                $ffi->_exit(1);
            }

            \posix_kill(\posix_getpid(), SIGKILL);
        }

        // Assert
        try {
            \pcntl_waitpid($pid, $status);
            $this->assertTrue(\pcntl_wifexited($status));
            // Exit code 0 means the child observed FD_CLOEXEC on the descriptor
            $this->assertSame(0, \pcntl_wexitstatus($status));
        } finally {
            \fclose($resource);
        }
    }
}
