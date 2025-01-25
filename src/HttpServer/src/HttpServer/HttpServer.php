<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\HttpServer;

use Amp\Http\Server\Driver\ConnectionLimitingClientFactory;
use Amp\Http\Server\Driver\ConnectionLimitingServerSocketFactory;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerTlsContext;
use Amp\Sync\LocalSemaphore;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\Plugin\System\Connections\NetworkTrafficCounter;
use PHPStreamServer\Plugin\HttpServer\Internal\ClientSocketFactory;
use PHPStreamServer\Plugin\HttpServer\Internal\Middleware\AccessLoggerMiddleware;
use PHPStreamServer\Plugin\HttpServer\Internal\Middleware\PhpSSMiddleware;
use PHPStreamServer\Plugin\HttpServer\Internal\Middleware\StaticMiddleware;
use PHPStreamServer\Plugin\HttpServer\Internal\TrafficCountingSocketFactory;
use PHPStreamServer\Plugin\HttpServer\Listen;

final readonly class HttpServer
{
    private const DEFAULT_TCP_BACKLOG = 65536;
    private const DEFAULT_CHUNK_SIZE = 16384;

    private SocketHttpServer $socketHttpServer;
    private HttpErrorHandler $errorHandler;

    /**
     * @param array<Listen> $listen
     * @param array<Middleware> $middleware
     * @param positive-int|null $connectionLimit
     * @param positive-int|null $connectionLimitPerIp
     * @param positive-int|null $concurrencyLimit
     * @param positive-int $connectionTimeout
     * @param positive-int $headerSizeLimit
     * @param positive-int $bodySizeLimit
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
        private LoggerInterface $logger,
        private NetworkTrafficCounter $networkTrafficCounter,
        private \Closure $reloadStrategyTrigger,
        private bool $accessLog,
        private string|null $serveDir,
    ) {
        $middleware = [];
        $this->errorHandler = new HttpErrorHandler($this->logger);
        $serverSocketFactory = new ResourceServerSocketFactory(self::DEFAULT_CHUNK_SIZE);
        $clientSocketFactory = new ClientSocketFactory($this->logger);

        if ($this->connectionLimitPerIp !== null) {
            $clientSocketFactory = new ConnectionLimitingClientFactory($clientSocketFactory, $this->logger, $this->connectionLimitPerIp);
        }

        if ($this->connectionLimit !== null) {
            $serverSocketFactory = new ConnectionLimitingServerSocketFactory(new LocalSemaphore($this->connectionLimit), $serverSocketFactory);
        }

        $serverSocketFactory = new TrafficCountingSocketFactory($serverSocketFactory, $this->networkTrafficCounter);

        if ($this->concurrencyLimit !== null) {
            $middleware[] = new Middleware\ConcurrencyLimitingMiddleware($this->concurrencyLimit);
        }

        if ($this->accessLog) {
            $middleware[] = new AccessLoggerMiddleware($this->logger);
        }

        $middleware = [...$middleware, ...$this->middleware];

        $middleware[] = new PhpSSMiddleware($this->errorHandler, $this->networkTrafficCounter, $this->reloadStrategyTrigger);

        // StaticMiddleware must be at the end of the chain
        if ($this->serveDir !== null) {
            $middleware[] = new StaticMiddleware($this->serveDir);
        }

        $this->socketHttpServer = new SocketHttpServer(
            logger: $this->logger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientSocketFactory,
            middleware: $middleware,
            allowedMethods: null,
            httpDriverFactory: new DefaultHttpDriverFactory(
                logger: $this->logger,
                streamTimeout: $this->connectionTimeout,
                connectionTimeout: $this->connectionTimeout,
                headerSizeLimit: $this->headerSizeLimit,
                bodySizeLimit: $this->bodySizeLimit,
                http2Enabled: $this->http2Enabled,
                pushEnabled: true,
            ),
        );

        foreach ($this->listen as $listen) {
            /** @psalm-suppress TooFewArguments */
            $this->socketHttpServer->expose(...self::createInternetAddressAndContext($listen, true, self::DEFAULT_TCP_BACKLOG));
        }
    }

    public function start(): void
    {
        $this->socketHttpServer->start($this->requestHandler, $this->errorHandler);
    }

    public function stop(): void
    {
        $this->socketHttpServer->stop();
    }

    /**
     * @return array{0: InternetAddress, 1: BindContext}
     */
    public static function createInternetAddressAndContext(Listen $listen, bool $reusePort = false, int $backlog = 0): array
    {
        $internetAddress = new InternetAddress($listen->host, $listen->port);
        $context = new BindContext();

        if ($reusePort) {
            $context = $context->withReusePort();
        }

        if ($backlog > 0) {
            $context = $context->withBacklog($backlog);
        }

        if ($listen->tls) {
            \assert($listen->tlsCertificate !== null);
            $cert = new Certificate($listen->tlsCertificate, $listen->tlsCertificateKey);
            $tlsContext = (new ServerTlsContext())->withDefaultCertificate($cert);
            $context = $context->withTlsContext($tlsContext);
        }

        return [$internetAddress, $context];
    }
}
