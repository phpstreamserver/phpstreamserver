<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\Worker;

use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use PHPStreamServer\Core\Exception\ServiceNotFoundException;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Plugin\System\Connections\NetworkTrafficCounter;
use PHPStreamServer\Core\ReloadStrategy\ReloadStrategy;
use PHPStreamServer\Core\Worker\WorkerProcess;
use PHPStreamServer\Plugin\HttpServer\HttpServer\HttpServer;
use PHPStreamServer\Plugin\HttpServer\HttpServerPlugin;
use PHPStreamServer\Plugin\HttpServer\Internal\Middleware\MetricsMiddleware;
use PHPStreamServer\Plugin\HttpServer\Listen;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use function PHPStreamServer\Core\getCpuCount;

class HttpServerProcess extends WorkerProcess
{
    private HttpServer $httpServer;

    /**
     * @param Listen|string|array<Listen> $listen
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(Request, self): Response $onRequest
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     * @param array<Middleware> $middleware
     * @param array<ReloadStrategy> $reloadStrategies
     * @param positive-int|null $connectionLimit
     * @param positive-int|null $connectionLimitPerIp
     * @param positive-int|null $concurrencyLimit
     */
    public function __construct(
        private Listen|string|array $listen,
        string $name = 'HTTP Server',
        int|null $count = null,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        \Closure|null $onStart = null,
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
            count: $count ?? getCpuCount(),
            reloadable: $reloadable,
            user: $user,
            group: $group,
            onStart: $onStart,
            onStop: $onStop,
            onReload: $onReload,
            reloadStrategies: $reloadStrategies,
        );

        $this->onStart($this->startServer(...));
        $this->onStop($this->stopServer(...), -1000);
        $this->onReload($this->stopServer(...), -1000);
    }

    public static function handleBy(): array
    {
        return [...parent::handleBy(), HttpServerPlugin::class];
    }

    private function startServer(): void
    {
        if ($this->onRequest !== null) {
            $requestHandler = $this->onRequest;
        } elseif ($this->container->has('request_handler')) {
            $requestHandler = $this->container->get('request_handler');
        } else {
            $requestHandler = new ClosureRequestHandler(static fn(): never => throw new HttpErrorException(404));
        }

        if ($requestHandler instanceof \Closure) {
            $requestHandler = new class ($requestHandler, $this) implements RequestHandler {
                public function __construct(private readonly \Closure $handler, private WorkerProcess $worker)
                {
                }

                public function handleRequest(Request $request): Response
                {
                    return ($this->handler)($request, $this->worker);
                }
            };
        }

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
            } catch (ServiceNotFoundException) {
            }
        }

        $networkTrafficCounter = new NetworkTrafficCounter($this->container->getService(MessageBusInterface::class));

        if ($this->serverDir !== null) {
            $serverDir = $this->serverDir;
        } elseif($this->container->hasParameter('server_dir')) {
            $serverDir = $this->container->getParameter('server_dir');
        } else {
            $serverDir = null;
        }

        $reloadStrategyEmitter = $this->container->getService('reload_strategy_emitter');

        /**
         * @psalm-suppress InvalidArgument
         */
        $this->httpServer = new HttpServer(
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
            logger: $this->logger->withChannel('http'),
            networkTrafficCounter: $networkTrafficCounter,
            reloadStrategyTrigger: $reloadStrategyEmitter,
            accessLog: $this->accessLog,
            serveDir: $serverDir,
        );

        $this->httpServer->start();
    }

    private function stopServer(): void
    {
        if (isset($this->httpServer)) {
            $this->httpServer->stop();
        }
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
