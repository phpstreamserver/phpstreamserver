<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\Internal;

use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\Cancellation;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;
use PHPStreamServer\Core\Plugin\System\Connections\NetworkTrafficCounter;

/**
 * @implements \IteratorAggregate<int, string>
 * @internal
 */
final readonly class TrafficCountingSocket implements Socket, \IteratorAggregate
{
    use ReadableStreamIteratorAggregate;

    public function __construct(private Socket $socket, private NetworkTrafficCounter $trafficStatisticStore)
    {
        $this->trafficStatisticStore->addConnection($this);

        $socket->onClose(function () {
            $this->trafficStatisticStore->removeConnection($this);
        });
    }

    public function close(): void
    {
        $this->socket->close();
    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->socket->onClose($onClose);
    }

    public function isReadable(): bool
    {
        return $this->socket->isReadable();
    }

    public function read(Cancellation|null $cancellation = null, int|null $limit = null): string|null
    {
        $bytes = $this->socket->read($cancellation, $limit);

        if ($bytes !== null) {
            $this->trafficStatisticStore->incRx($this, \strlen($bytes));
        }

        return $bytes;
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function setupTls(Cancellation|null $cancellation = null): void
    {
        $this->socket->setupTls($cancellation);
    }

    public function shutdownTls(Cancellation|null $cancellation = null): void
    {
        $this->socket->shutdownTls($cancellation);
    }

    public function isTlsConfigurationAvailable(): bool
    {
        return $this->socket->isTlsConfigurationAvailable();
    }

    public function getTlsState(): TlsState
    {
        return $this->socket->getTlsState();
    }

    public function getTlsInfo(): TlsInfo|null
    {
        return $this->socket->getTlsInfo();
    }

    public function write(string $bytes): void
    {
        $this->socket->write($bytes);
        $this->trafficStatisticStore->incTx($this, \strlen($bytes));
    }

    public function end(): void
    {
        $this->socket->end();
    }

    public function isWritable(): bool
    {
        return $this->socket->isWritable();
    }
}
