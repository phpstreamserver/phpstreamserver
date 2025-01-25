<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\GelfTransport;

use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableStream;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\DnsSocketConnector;
use PHPStreamServer\Plugin\Logger\Internal\NullWritableStream;
use Revolt\EventLoop;

/**
 * @internal
 */
final class GelfTcpTransport implements GelfTransport
{
    private const CONNECT_TIMEOUT = 4;
    private const RECONNECT_TIMEOUT = 10;

    private WritableStream $socket;
    private bool $inErrorState = false;

    public function __construct(private readonly string $host, private readonly int $port)
    {
    }

    public function start(): void
    {
        $connector = new DnsSocketConnector();
        $context = (new ConnectContext())->withConnectTimeout(self::CONNECT_TIMEOUT);

        try {
            $this->socket = $connector->connect(\sprintf('tcp://%s:%d', $this->host, $this->port), $context);
            $this->inErrorState = false;
        } catch (ConnectException $e) {
            $this->socket = new NullWritableStream();

            if ($this->inErrorState === false) {
                \trigger_error($e->getMessage(), E_USER_WARNING);
                $this->inErrorState = true;
            }

            EventLoop::delay(self::RECONNECT_TIMEOUT, function () {
                $this->start();
            });
        }
    }

    public function write(string $buffer): void
    {
        try {
            $this->socket->write($buffer . "\0");
        } catch (StreamException) {
            $this->start();
            // try to send second time after connect
            try {
                $this->socket->write($buffer . "\0");
            } catch (StreamException) {
                // do nothing
            }
        }
    }
}
