<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\ResourceServerSocketFactory;
use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\Logger\NullLogger;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Plugin\HttpServer\HttpServer\HttpErrorHandler;
use PHPStreamServer\Plugin\HttpServer\HttpServer\HttpServer;
use PHPStreamServer\Plugin\HttpServer\Listen;
use PHPStreamServer\Plugin\Metrics\Internal\MessageBusRegistry;
use PHPStreamServer\Plugin\Metrics\Internal\MessageBusRegistryHandler;
use PHPStreamServer\Plugin\Metrics\Internal\NotFoundPage;
use Revolt\EventLoop;

final class MetricsPlugin extends Plugin
{
    public function __construct(
        private readonly Listen|string $listen,
    ) {
    }

    public function onStart(): void
    {
        $listen = \is_string($this->listen) ? new Listen($this->listen) : $this->listen;

        $this->masterContainer->setService(
            RegistryInterface::class,
            new MessageBusRegistry($this->masterContainer->getService(MessageBusInterface::class)),
        );

        $this->workerContainer->registerService(RegistryInterface::class, static function (ContainerInterface $container): RegistryInterface {
            return new MessageBusRegistry($container->getService(MessageBusInterface::class));
        });

        $logger = &$this->masterContainer->getService(LoggerInterface::class);
        $handler = &$this->masterContainer->getService(MessageHandlerInterface::class);

        $nullLogger = new NullLogger();
        $serverSocketFactory = new ResourceServerSocketFactory();
        $clientFactory = new SocketClientFactory($nullLogger);
        $errorHandler = new HttpErrorHandler($nullLogger);
        $socketHttpServer = new SocketHttpServer(
            logger: $nullLogger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientFactory,
            allowedMethods: ['GET'],
            httpDriverFactory: new DefaultHttpDriverFactory(logger: $nullLogger),
        );

        /**
         * @psalm-suppress TooFewArguments
         */
        $socketHttpServer->expose(...HttpServer::createInternetAddressAndContext($listen));

        $messageBusRegistryHandler = new MessageBusRegistryHandler($handler);

        EventLoop::defer(function () use (&$logger, $socketHttpServer, $messageBusRegistryHandler, $errorHandler, $listen) {
            $requestHandler = $this->createRequestHandler($messageBusRegistryHandler);
            $socketHttpServer->start($requestHandler, $errorHandler);
            $logger->info(\sprintf('Prometheus metrics available on %s/metrics', $listen->getAddress()));
        });
    }

    private function createRequestHandler(MessageBusRegistryHandler $messageBusRegistryHandler): RequestHandler
    {
        return new class ($messageBusRegistryHandler) implements RequestHandler {
            public function __construct(private readonly MessageBusRegistryHandler $messageBusRegistryHandler)
            {
            }

            public function handleRequest(Request $request): Response
            {
                if ($request->getUri()->getPath() !== '/metrics') {
                    return new Response(body: (new NotFoundPage())->toHtml(), status: HttpStatus::NOT_FOUND);
                }

                $result = $this->messageBusRegistryHandler->render();
                $headers = ['content-type' => 'text/plain; version=0.0.4'];

                return new Response(body: $result, headers: $headers);
            }
        };
    }
}
