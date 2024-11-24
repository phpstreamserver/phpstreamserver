<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer;

use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Plugin\Supervisor\ReloadStrategy\ReloadStrategyInterface;
use PHPStreamServer\Core\Plugin\Supervisor\WorkerProcess;
use PHPStreamServer\Core\Plugin\System\Connections\NetworkTrafficCounter;
use PHPStreamServer\Plugin\HttpServer\HttpServer\HttpServer;
use PHPStreamServer\Plugin\HttpServer\Internal\Middleware\MetricsMiddleware;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use Psr\Container\NotFoundExceptionInterface;

final class HttpServerProcess extends WorkerProcess
{
    private mixed $context = null;

    /**
     * @param Listen|string|array<Listen> $listen
     * @param null|\Closure(self, mixed):void $onStart
     * @param null|\Closure(Request, mixed): Response $onRequest
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     * @param array<Middleware> $middleware
     * @param array<ReloadStrategyInterface> $reloadStrategies
     * @param positive-int|null $connectionLimit
     * @param positive-int|null $connectionLimitPerIp
     * @param positive-int|null $concurrencyLimit
     */
    public function __construct(
        private Listen|string|array $listen,
        string $name = 'HTTP Server',
        int $count = 1,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onRequest = null,
        \Closure|null $onStop = null,
        \Closure|null $onReload = null,
        private array $middleware = [],
        array $reloadStrategies = [],
        private string|null $serverDir = null,
        private bool $accessLog = true,
        private bool $gzip = false,
        private int|null $connectionLimit = null,
        private int|null $connectionLimitPerIp = null,
        private int|null $concurrencyLimit = null,
    ) {
        parent::__construct(
            name: $name,
            count: $count,
            reloadable: $reloadable,
            user: $user,
            group: $group,
            onStart: $this->onStart(...),
            onStop: $onStop,
            onReload: $onReload,
            reloadStrategies: $reloadStrategies,
        );
    }

    public static function handleBy(): array
    {
        return [...parent::handleBy(), HttpServerPlugin::class];
    }

    private function onStart(): void
    {
        if ($this->onStart !== null) {
            ($this->onStart)($this, $this->context);
        }

        $this->onRequest ??= static fn(): never => throw new HttpErrorException(404);

        $requestHandler = new class ($this->onRequest, $this->context) implements RequestHandler {
            public function __construct(private readonly \Closure $handler, private mixed &$context)
            {
            }

            public function handleRequest(Request $request): Response
            {
                return ($this->handler)($request, $this->context);
            }
        };

        $networkTrafficCounter = new NetworkTrafficCounter($this->container->getService(MessageBusInterface::class));

        $middleware = [];

        if ($this->gzip) {
            /** @psalm-suppress InvalidArgument */
            $gzipMinLength = $this->container->getParameter('httpServerPlugin.gzipMinLength');
            /** @psalm-suppress InvalidArgument */
            $gzipTypesRegex = $this->container->getParameter('httpServerPlugin.gzipTypesRegex');
            /** @psalm-suppress InvalidArgument */
            $middleware[] = new Middleware\CompressionMiddleware($gzipMinLength, $gzipTypesRegex);
        }

        if (\interface_exists(RegistryInterface::class)) {
            try {
                $registry = $this->container->getService(RegistryInterface::class);
                $middleware[] = new MetricsMiddleware($registry);
            } catch (NotFoundExceptionInterface) {
            }
        }

        /**
         * @psalm-suppress InvalidArgument
         */
        $httpServer = new HttpServer(
            listen: self::normalizeListenList($this->listen),
            requestHandler: $requestHandler,
            middleware: [...$middleware, ...$this->middleware],
            connectionLimit: $this->connectionLimit,
            connectionLimitPerIp: $this->connectionLimitPerIp,
            concurrencyLimit: $this->concurrencyLimit,
            http2Enabled: $this->container->getParameter('httpServerPlugin.http2Enable'),
            connectionTimeout: $this->container->getParameter('httpServerPlugin.httpConnectionTimeout'),
            headerSizeLimit: $this->container->getParameter('httpServerPlugin.httpHeaderSizeLimit'),
            bodySizeLimit: $this->container->getParameter('httpServerPlugin.httpBodySizeLimit'),
            logger: $this->logger,
            networkTrafficCounter: $networkTrafficCounter,
            reloadStrategyTrigger: $this->reloadStrategyTrigger,
            accessLog: $this->accessLog,
            serveDir: $this->serverDir,
        );

        $httpServer->start();
    }

    /**
     * @return list<Listen>
     */
    private static function normalizeListenList(Listen|string|array $listen): array
    {
        $listen = \is_array($listen) ? $listen : [$listen];
        $ret = [];
        foreach ($listen as $listenItem) {
            if ($listenItem instanceof Listen) {
                $ret[] = $listenItem;
            } elseif (\is_string($listenItem)) {
                $ret[] = new Listen($listenItem);
            } else {
                throw new \InvalidArgumentException('Invalid listen');
            }
        }

        return $ret;
    }
}
