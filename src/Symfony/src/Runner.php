<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Plugin\HttpServer\HttpServerPlugin;
use PHPStreamServer\Plugin\HttpServer\HttpServerProcess;
use PHPStreamServer\Symfony\Event\HttpServerStartedEvent;
use Symfony\Component\Runtime\RunnerInterface;
use PHPStreamServer\Core\Server;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class Runner implements RunnerInterface
{
    public function __construct(private KernelFactory $kernelFactory)
    {
    }

    public function run(): int
    {
        $server = new Server();

        $server->addPlugin(new HttpServerPlugin(
            httpHeaderSizeLimit: 32768,
            httpBodySizeLimit: 157286400,
        ));

        $server->addWorker(
            new HttpServerProcess(
                count: 1,
                listen: '0.0.0.0:80',
                onStart: function (HttpServerProcess $worker): void {
                    $kernel = $this->kernelFactory->createKernel();
                    $kernel->boot();

                    /** @var EventDispatcherInterface $eventDispatcher */
                    $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
                    $eventDispatcher->dispatch(new HttpServerStartedEvent($worker));
                },
                onRequest: function (Request $request, HttpServerProcess $worker): Response {
                    return ($worker->container->get('http_handler'))($request);
                },
                serverDir: $this->kernelFactory->projectDir . '/public',
            ),
        );

        return $server->run();





//        $configLoader = new ConfigLoader(
//            projectDir: $this->kernelFactory->getProjectDir(),
//            cacheDir: $this->kernelFactory->getCacheDir(),
//            isDebug: $this->kernelFactory->isDebug(),
//        );
//
//        $config = $configLoader->getConfig($this->kernelFactory);
//
//        $server = new Server(
//            pidFile: $config['pid_file'],
//            stopTimeout: $config['stop_timeout'],
//        );
//
//        foreach ($config['servers'] as $serverConfig) {
//            $server->addWorkers(new HttpServerWorker(
//                kernelFactory: $this->kernelFactory,
//                listen: $serverConfig['listen'],
//                localCert: $serverConfig['local_cert'],
//                localPk: $serverConfig['local_pk'],
//                name: $serverConfig['name'] ?? 'Webserver',
//                count: $serverConfig['processes'] ?? Functions::cpuCount() * 2,
//                user: $config['user'],
//                group: $config['group'],
//                maxBodySize: $serverConfig['max_body_size'],
//            ));
//        }
//
//        if (!empty($config['tasks'])) {
//            $server->addWorkers(new SchedulerWorker(
//                kernelFactory: $this->kernelFactory,
//                user: $config['user'],
//                group: $config['group'],
//                tasks: $config['tasks'],
//            ));
//        }
//
//        foreach ($config['processes'] as $processConfig) {
//            $server->addWorkers(new ProcessWorker(
//                kernelFactory: $this->kernelFactory,
//                user: $config['user'],
//                group: $config['group'],
//                name: $processConfig['name'],
//                command: $processConfig['command'],
//                count: $processConfig['count'],
//            ));
//        }
//
//        if ($config['reload_strategy']['on_file_change']['active']) {
//            $server->addWorkers(new FileMonitorWorker(
//                sourceDir: $config['reload_strategy']['on_file_change']['source_dir'],
//                filePattern: $config['reload_strategy']['on_file_change']['file_pattern'],
//                pollingInterval: $config['reload_strategy']['on_file_change']['polling_interval'],
//                user: $config['user'],
//                group: $config['group'],
//                reloadCallback: $server->reload(...),
//            ));
//        }
//
//        return $server->run();
    }
}
