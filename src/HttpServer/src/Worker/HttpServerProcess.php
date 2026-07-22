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
    private HttpServer|null $httpServer = null;

    /**
     * @param Listen|string|array<Listen> $listen
     * @param null|\Closure(static):void $onStart
     * @param null|\Closure(Request, static): Response $onRequest
     * @param null|\Closure(static):void $onStop
     * @param null|\Closure(static):void $onReload
     * @param array<Middleware> $middleware
     * @param array<ReloadStrategy> $reloadStrategies
     * @param positive-int|null $connectionLimit
     * @param positive-int|null $connectionLimitPerIp
     * @param positive-int|null $concurrencyLimit
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function __construct(
        private readonly Listen|string|array $listen,
        string $name = 'HTTP Server',
        int|null $count = null,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        \Closure|null $onStart = null,
        private readonly \Closure|null $onRequest = null,
        \Closure|null $onStop = null,
        \Closure|null $onReload = null,
        private readonly array $middleware = [],
        array $reloadStrategies = [],
        private readonly string|null $documentRoot = null,
        private readonly bool $accessLog = true,
        private readonly bool $gzip = false,
        private readonly int|null $connectionLimit = null,
        private readonly int|null $connectionLimitPerIp = null,
        private readonly int|null $concurrencyLimit = null,
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

        $this->onStart(static fn(self $worker) => self::startServer($worker));
        $this->onStop(static fn(self $worker) => self::stopServer($worker), -1000);
        $this->onReload(static fn(self $worker) => self::stopServer($worker), -1000);
    }

    public static function handledBy(): array
    {
        return [...parent::handledBy(), HttpServerPlugin::class];
    }

    private static function startServer(self $worker): void
    {
        $requestHandler = match (true) {
            $worker->onRequest !== null => $worker->onRequest,
            $worker->container->has('request_handler') => $worker->container->get('request_handler'),
            default => new ClosureRequestHandler(static fn(): never => throw new HttpErrorException(404)),
        };

        if ($requestHandler instanceof \Closure) {
            $requestHandler = new class ($requestHandler, $worker) implements RequestHandler {
                public function __construct(private readonly \Closure $handler, private readonly WorkerProcess $worker)
                {
                }

                public function handleRequest(Request $request): Response
                {
                    return ($this->handler)($request, $this->worker);
                }
            };
        }

        $middleware = [];

        if ($worker->gzip) {
            /** @psalm-suppress InvalidArgument */
            $gzipMinLength = $worker->container->getParameter('httpServerPlugin.gzipMinLength');
            /** @psalm-suppress InvalidArgument */
            $gzipTypesRegex = $worker->container->getParameter('httpServerPlugin.gzipTypesRegex');
            /** @psalm-suppress InvalidArgument */
            $middleware[] = new Middleware\CompressionMiddleware($gzipMinLength, $gzipTypesRegex);
        }

        if (\interface_exists(RegistryInterface::class)) {
            try {
                $registry = $worker->container->getService(RegistryInterface::class);
                $middleware[] = new MetricsMiddleware($registry);
            } catch (ServiceNotFoundException) {
                // no action
            }
        }

        $networkTrafficCounter = new NetworkTrafficCounter($worker->container->getService(MessageBusInterface::class));

        $documentRoot = match (true) {
            $worker->documentRoot !== null => $worker->documentRoot,
            $worker->container->hasParameter('document_root') => $worker->container->getParameter('document_root'),
            default => null,
        };

        /** @var \Closure $reloadStrategyEmitter */
        $reloadStrategyEmitter = $worker->container->getService('reload_strategy_emitter');

        /** @psalm-suppress InvalidArgument */
        $worker->httpServer = new HttpServer(
            listen: self::normalizeListenList($worker->listen),
            requestHandler: $requestHandler,
            middleware: [...$middleware, ...$worker->middleware],
            connectionLimit: $worker->connectionLimit,
            connectionLimitPerIp: $worker->connectionLimitPerIp,
            concurrencyLimit: $worker->concurrencyLimit,
            http2Enabled: $worker->container->getParameter('httpServerPlugin.http2Enabled'),
            connectionTimeout: $worker->container->getParameter('httpServerPlugin.httpConnectionTimeout'),
            headerSizeLimit: $worker->container->getParameter('httpServerPlugin.httpHeaderSizeLimit'),
            bodySizeLimit: $worker->container->getParameter('httpServerPlugin.httpBodySizeLimit'),
            logger: $worker->logger->withChannel('http'),
            networkTrafficCounter: $networkTrafficCounter,
            reloadStrategyTrigger: $reloadStrategyEmitter,
            accessLog: $worker->accessLog,
            documentRoot: $documentRoot,
        );

        $worker->httpServer->start();
    }

    private static function stopServer(self $worker): void
    {
        $worker->httpServer?->stop();
        $worker->httpServer = null;
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
                throw new \InvalidArgumentException('Invalid listen value: expected a string or an instance of Listen');
            }
        }

        return $ret;
    }
}
