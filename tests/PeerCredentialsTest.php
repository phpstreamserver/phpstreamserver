<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Internal\PeerCredentials;
use PHPUnit\Framework\TestCase;

final class PeerCredentialsTest extends TestCase
{
    public function testReturnsCredentialsFromResource(): void
    {
        // Arrange
        $pair = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        // Assert
        try {
            $credentials0 = PeerCredentials::get($pair[0]);
            $credentials1 = PeerCredentials::get($pair[1]);

            $this->assertInstanceOf(PeerCredentials::class, $credentials0);
            $this->assertInstanceOf(PeerCredentials::class, $credentials1);

            $this->assertSame(\posix_getpid(), $credentials0->pid);
            $this->assertSame(\posix_geteuid(), $credentials0->uid);
            $this->assertSame(\posix_getegid(), $credentials0->gid);

            $this->assertSame(\posix_getpid(), $credentials1->pid);
            $this->assertSame(\posix_geteuid(), $credentials1->uid);
            $this->assertSame(\posix_getegid(), $credentials1->gid);
        } finally {
            \fclose($pair[0]);
            \fclose($pair[1]);
        }
    }

    public function testReturnsCredentialsForConnectingProcess(): void
    {
        // Arrange
        $socketPath = \sys_get_temp_dir() . '/phpss-test-' . \uniqid() . '.sock';
        $server = \stream_socket_server('unix://' . $socketPath);

        $pid = \pcntl_fork();
        $this->assertNotSame(-1, $pid, 'Failed to fork child process');

        if ($pid === 0) {
            // Forked process
            $client = \stream_socket_client(address: 'unix://' . $socketPath, timeout: 2);
            \fread($client, 1);
            \fclose($client);
            \posix_kill(\posix_getpid(), SIGKILL); // Do not use exit directly to prevent shutdown callback execute
        }

        // Assert
        try {
            $conn = \stream_socket_accept(socket: $server, timeout: 2);
            $credentials = PeerCredentials::get($conn);
            \fwrite($conn, 'a');
            \fclose($conn);

            $this->assertInstanceOf(PeerCredentials::class, $credentials);
            $this->assertSame($pid, $credentials->pid);
            $this->assertSame(\posix_geteuid(), $credentials->uid);
            $this->assertSame(\posix_getegid(), $credentials->gid);
        } finally {
            \fclose($server);
            \unlink($socketPath);
            \pcntl_waitpid($pid, $status);
        }
    }

    public function testReturnsNullForInvalidInput(): void
    {
        // Assert
        $this->assertNull(PeerCredentials::get(\fopen('php://memory', 'r')));
    }
}
