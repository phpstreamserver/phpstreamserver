<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\Internal;

use Amp\Cancellation;
use Amp\Socket\BindContext;
use Amp\Socket\ServerSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use PHPStreamServer\Core\Plugin\System\Connections\NetworkTrafficCounter;

/**
 * @internal
 */
final readonly class TrafficCountingSocketFactory implements ServerSocketFactory
{
    public function __construct(
        private ServerSocketFactory $socketServerFactory,
        private NetworkTrafficCounter $networkTrafficCounter,
    ) {
    }

    public function listen(SocketAddress|string $address, BindContext|null $bindContext = null): ServerSocket
    {
        $serverSocket = $this->socketServerFactory->listen($address, $bindContext);

        return new class ($serverSocket, $this->networkTrafficCounter) implements ServerSocket {
            public function __construct(private readonly ServerSocket $serverSocket, private readonly NetworkTrafficCounter $networkTrafficCounter)
            {
            }

            public function close(): void
            {
                $this->serverSocket->close();
            }

            public function isClosed(): bool
            {
                return $this->serverSocket->isClosed();
            }

            public function onClose(\Closure $onClose): void
            {
                $this->serverSocket->onClose($onClose);
            }

            public function accept(Cancellation|null $cancellation = null): Socket|null
            {
                if (null === $socket = $this->serverSocket->accept($cancellation)) {
                    return null;
                }

                return new TrafficCountingSocket($socket, $this->networkTrafficCounter);
            }

            public function getAddress(): SocketAddress
            {
                return $this->serverSocket->getAddress();
            }

            public function getBindContext(): BindContext
            {
                return $this->serverSocket->getBindContext();
            }
        };
    }
}
