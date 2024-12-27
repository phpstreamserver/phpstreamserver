<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle;

use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServerBundle\Internal\Functions;
use Luzrain\PHPStreamServerBundle\Worker\FileMonitorWorker;
use Luzrain\PHPStreamServerBundle\Worker\HttpServerWorker;
use Luzrain\PHPStreamServerBundle\Worker\ProcessWorker;
use Luzrain\PHPStreamServerBundle\Worker\SchedulerWorker;
use Symfony\Component\Runtime\RunnerInterface;

final readonly class Runner implements RunnerInterface
{
    public function __construct(private KernelFactory $kernelFactory)
    {
    }

    public function run(): int
    {
        $configLoader = new ConfigLoader(
            projectDir: $this->kernelFactory->getProjectDir(),
            cacheDir: $this->kernelFactory->getCacheDir(),
            isDebug: $this->kernelFactory->isDebug(),
        );

        $config = $configLoader->getConfig($this->kernelFactory);

        $server = new Server(
            pidFile: $config['pid_file'],
            stopTimeout: $config['stop_timeout'],
        );

        foreach ($config['servers'] as $serverConfig) {
            $server->addWorkers(new HttpServerWorker(
                kernelFactory: $this->kernelFactory,
                listen: $serverConfig['listen'],
                localCert: $serverConfig['local_cert'],
                localPk: $serverConfig['local_pk'],
                name: $serverConfig['name'] ?? 'Webserver',
                count: $serverConfig['processes'] ?? Functions::cpuCount() * 2,
                user: $config['user'],
                group: $config['group'],
                maxBodySize: $serverConfig['max_body_size'],
            ));
        }

        if (!empty($config['tasks'])) {
            $server->addWorkers(new SchedulerWorker(
                kernelFactory: $this->kernelFactory,
                user: $config['user'],
                group: $config['group'],
                tasks: $config['tasks'],
            ));
        }

        foreach ($config['processes'] as $processConfig) {
            $server->addWorkers(new ProcessWorker(
                kernelFactory: $this->kernelFactory,
                user: $config['user'],
                group: $config['group'],
                name: $processConfig['name'],
                command: $processConfig['command'],
                count: $processConfig['count'],
            ));
        }

        if ($config['reload_strategy']['on_file_change']['active']) {
            $server->addWorkers(new FileMonitorWorker(
                sourceDir: $config['reload_strategy']['on_file_change']['source_dir'],
                filePattern: $config['reload_strategy']['on_file_change']['file_pattern'],
                pollingInterval: $config['reload_strategy']['on_file_change']['polling_interval'],
                user: $config['user'],
                group: $config['group'],
                reloadCallback: $server->reload(...),
            ));
        }

        return $server->run();
    }
}
