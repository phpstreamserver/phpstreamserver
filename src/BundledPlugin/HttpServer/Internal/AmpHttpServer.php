<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal;

use Amp\Http\Server\Driver\ConnectionLimitingServerSocketFactory;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Middleware\ConcurrencyLimitingMiddleware;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerTlsContext;
use Amp\Sync\LocalSemaphore;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Listen;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Middleware\StaticMiddleware;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal\ReloadStrategy\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\NetworkTrafficCounter;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final readonly class AmpHttpServer
{
    private const DEFAULT_TCP_BACKLOG = 65536;

    /**
     * @param array<Listen> $listen
     * @param array<Middleware> $middleware
     * @param positive-int|null $connectionLimit
     * @param positive-int|null $connectionLimitPerIp
     * @param positive-int|null $concurrencyLimit
     */
    public function __construct(
        private array $listen,
        private RequestHandler $requestHandler,
        private array $middleware,
        private int|null $connectionLimit,
        private int|null $connectionLimitPerIp,
        private int|null $concurrencyLimit,
        private bool $http2Enabled,
        private int $connectionTimeout,
        private int $headerSizeLimit,
        private int $bodySizeLimit,
        private \Closure|null $onConnect = null,
        private \Closure|null $onClose = null,
    ) {
    }

    public function start(
        LoggerInterface $logger,
        NetworkTrafficCounter $networkTrafficCounter,
        ReloadStrategyTrigger $reloadStrategyTrigger,
    ): void {
        $middleware = [];
        $errorHandler = new HttpErrorHandler($logger);
        $serverSocketFactory = new ResourceServerSocketFactory();
        $clientFactory = new HttpClientFactory(
            logger: $logger,
            connectionLimitPerIp: $this->connectionLimitPerIp,
            onConnectCallback: $this->onConnect,
            onCloseCallback: $this->onClose,
        );

        if ($this->connectionLimit !== null) {
            $serverSocketFactory = new ConnectionLimitingServerSocketFactory(new LocalSemaphore($this->connectionLimit), $serverSocketFactory);
        }

        $serverSocketFactory = new TrafficCountingSocketFactory($serverSocketFactory, $networkTrafficCounter);

        if ($this->concurrencyLimit !== null) {
            $middleware[] = new ConcurrencyLimitingMiddleware($this->concurrencyLimit);
        }

        $middleware[] = new AmpHttpServerMiddleware($errorHandler, $networkTrafficCounter, $reloadStrategyTrigger);

        \array_push($middleware, ...$this->middleware);

        // Force move StaticMiddleware to the end of the chain
        \usort($middleware, static fn (mixed $a): int => $a instanceof StaticMiddleware ? 1 : -1);

        $socketHttpServer = new SocketHttpServer(
            logger: $logger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientFactory,
            middleware: $middleware,
            allowedMethods: null,
            httpDriverFactory: new DefaultHttpDriverFactory(
                logger: $logger,
                streamTimeout: $this->connectionTimeout,
                connectionTimeout: $this->connectionTimeout,
                headerSizeLimit: $this->headerSizeLimit,
                bodySizeLimit: $this->bodySizeLimit,
                http2Enabled: $this->http2Enabled,
                pushEnabled: true,
            ),
        );

        foreach ($this->listen as $listen) {
            $socketHttpServer->expose(...$this->createInternetAddressAndContext($listen));
        }

        $socketHttpServer->start($this->requestHandler, $errorHandler);
    }

    /**
     * @return array{0: InternetAddress, 1: BindContext}
     */
    private function createInternetAddressAndContext(Listen $listen): array
    {
        $internetAddress = new InternetAddress($listen->host, $listen->port);

        $context = (new BindContext())
            ->withReusePort()
            ->withBacklog(self::DEFAULT_TCP_BACKLOG)
        ;

        if ($listen->tls) {
            \assert($listen->tlsCertificate !== null);
            $context = $context->withTlsContext(
                (new ServerTlsContext())->withDefaultCertificate(new Certificate($listen->tlsCertificate, $listen->tlsCertificateKey)),
            );
        }

        return [$internetAddress, $context];
    }
}