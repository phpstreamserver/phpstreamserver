<?php

declare(strict_types=1);

use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Core\Worker\ExecutableWorker;
use PHPStreamServer\Core\Worker\SupervisedWorker;
use PHPStreamServer\Plugin\HttpServer\HttpServerPlugin;
use PHPStreamServer\Plugin\HttpServer\Listen;
use PHPStreamServer\Plugin\HttpServer\Worker\HttpServerWorker;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;
use PHPStreamServer\Plugin\Scheduler\Worker\ScheduledWorker;
use PHPStreamServer\Test\data\TestPlugin\TestPlugin;

include __DIR__ . '/../../vendor/autoload.php';

$server = new Server();

$server->addPlugin(
    new HttpServerPlugin(),
    new SchedulerPlugin(),
    new TestPlugin(),
);

$server->addWorker(
    new SupervisedWorker(
        name: 'Worker 1',
        count: 2,
    ),
    new SupervisedWorker(
        name: 'Worker 2',
        count: 1,
    ),
    new ExecutableWorker(
        name: 'External 1',
        count: 1,
        command: 'sleep 3600',
    ),
    new ExecutableWorker(
        name: 'External 2',
        count: 1,
        command: 'sleep 3600',
        reloadable: false,
    ),
    new HttpServerWorker(
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
    new ScheduledWorker(
        name: 'Scheduled worker 1',
        schedule: '1 second',
        onStart: static function (ScheduledWorker $worker) {
            \file_put_contents(\sys_get_temp_dir() . '/phpss-test-9af00c2f.txt', \time() . "\n");
        },
    ),
);

exit($server->run());
