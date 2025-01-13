<?php

declare(strict_types=1);

use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Core\Worker\ExternalProcess;
use PHPStreamServer\Core\Worker\WorkerProcess;
use PHPStreamServer\Plugin\HttpServer\HttpServerPlugin;
use PHPStreamServer\Plugin\HttpServer\HttpServerProcess;
use PHPStreamServer\Plugin\HttpServer\Listen;
use PHPStreamServer\Plugin\Scheduler\PeriodicProcess;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;
use PHPStreamServer\Test\data\TestPlugin\TestPlugin;

include __DIR__ . '/../../vendor/autoload.php';

$server = new Server();

$server->addPlugin(
    new HttpServerPlugin(),
    new SchedulerPlugin(),
    new TestPlugin(),
);

$server->addWorker(
    new WorkerProcess(
        name: 'Worker Process 1',
        count: 2,
    ),
    new WorkerProcess(
        name: 'Worker Process 2',
        count: 1,
    ),
    new ExternalProcess(
        name: 'External Process 1',
        count: 1,
        command: 'sleep 3600',
    ),
    new ExternalProcess(
        name: 'External Process 2',
        count: 1,
        command: 'sleep 3600',
        reloadable: false,
    ),
    new HttpServerProcess(
        listen: [
            new Listen(listen: '127.0.0.1:9080'),
            new Listen(listen: '127.0.0.1:9081', tls: true, tlsCertificate: __DIR__ . '/localhost.crt'),
        ],
        name: 'HTTP Server',
        count: 1,
        reloadable: false,
        onRequest: static function (Request $request): Response {
            return match ($request->getUri()->getPath()) {
                '/' => new Response(body: 'Hello world'),
                '/error' => throw new \Exception('test exception'),
                default => throw new HttpErrorException(404),
            };
        },
    ),
    new PeriodicProcess(
        name: 'Periodic Process 1',
        schedule: '1 second',
        onStart: static function (PeriodicProcess $worker) {
            \file_put_contents(\sys_get_temp_dir() . '/phpss-test-9af00c2f.txt', \time() . "\n");
        },
    ),
);

exit($server->run());
