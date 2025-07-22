<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\Internal;

use Amp\CancelledException;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Driver\ClientFactory;
use Amp\Http\Server\Driver\SocketClient;
use Amp\Socket\Socket;
use Amp\Socket\SocketException;
use Amp\TimeoutCancellation;
use Psr\Log\LoggerInterface as PsrLogger;

/**
 * @internal
 */
final readonly class ClientSocketFactory implements ClientFactory
{
    public function __construct(
        private PsrLogger $logger,
        private float $tlsHandshakeTimeout = 5,
    ) {
    }

    public function createClient(Socket $socket): Client|null
    {
        if ($socket->isTlsConfigurationAvailable()) {
            try {
                $socket->setupTls(new TimeoutCancellation($this->tlsHandshakeTimeout));
            } catch (SocketException $exception) {
                $localAddress = \explode(':', $socket->getLocalAddress()->toString())[0];
                $remoteAddress = \explode(':', $socket->getRemoteAddress()->toString())[0];
                $message = $exception->getMessage();
                $message = \str_replace(['TLS negotiation failed:', 'stream_socket_enable_crypto():', "\n"], ['', '', ' '], $message);
                $message = \ltrim($message);
                $this->logger->notice(\sprintf('%s TLS negotiation failed: %s', $remoteAddress, $message), [
                    'local' => $localAddress,
                    'remote' => $remoteAddress,
                ]);

                return null;
            } catch (CancelledException) {
                $localAddress = \explode(':', $socket->getLocalAddress()->toString())[0];
                $remoteAddress = \explode(':', $socket->getRemoteAddress()->toString())[0];
                $this->logger->notice(\sprintf('%s TLS negotiation timed out', $remoteAddress), [
                    'local' => $localAddress,
                    'remote' => $remoteAddress,
                ]);

                return null;
            }
        }

        return new SocketClient($socket);
    }
}
