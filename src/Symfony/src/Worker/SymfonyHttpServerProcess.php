<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use Amp\Http\Server\Middleware;
use PHPStreamServer\Core\ReloadStrategy\ReloadStrategy;
use PHPStreamServer\Plugin\HttpServer\Listen;
use PHPStreamServer\Plugin\HttpServer\Worker\HttpServerProcess;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

final class SymfonyHttpServerProcess extends HttpServerProcess
{
    /**
     * @param Listen|string|array<Listen> $listen
     * @param array<Middleware> $middleware
     * @param array<ReloadStrategy> $reloadStrategies
     * @param positive-int|null $connectionLimit
     * @param positive-int|null $connectionLimitPerIp
     * @param positive-int|null $concurrencyLimit
     */
    public function __construct(
        Listen|string|array $listen,
        int|null $count = null,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        array $middleware = [],
        array $reloadStrategies = [],
        bool $accessLog = true,
        bool $gzip = false,
        int|null $connectionLimit = null,
        int|null $connectionLimitPerIp = null,
        int|null $concurrencyLimit = 1,
    ) {
        parent::__construct(
            listen: $listen,
            name: 'Symfony webserver',
            count: $count,
            reloadable: $reloadable,
            user: $user,
            group: $group,
            middleware: $middleware,
            reloadStrategies: $reloadStrategies,
            accessLog: $accessLog,
            gzip: $gzip,
            connectionLimit: $connectionLimit,
            connectionLimitPerIp: $connectionLimitPerIp,
            concurrencyLimit: $concurrencyLimit,
        );
    }

    public static function handledBy(): array
    {
        return [...parent::handledBy(), SymfonyPlugin::class];
    }
}
