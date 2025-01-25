<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use PHPStreamServer\Plugin\HttpServer\Listen;
use PHPStreamServer\Plugin\HttpServer\Worker\HttpServerProcess;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

final class SymfonyHttpServerProcess extends HttpServerProcess
{
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
        int|null $concurrencyLimit = null,
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

    public static function handleBy(): array
    {
        return [...parent::handleBy(), SymfonyPlugin::class];
    }
}
