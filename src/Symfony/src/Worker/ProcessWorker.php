<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Worker;

use Luzrain\PHPStreamServer\WorkerProcess;
use Luzrain\PHPStreamServerBundle\KernelFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class ProcessWorker extends WorkerProcess
{
    public function __construct(
        private readonly KernelFactory $kernelFactory,
        string|null $user,
        string|null $group,
        string|null $name,
        private readonly string $command,
        int $count,
    ) {
        parent::__construct(
            name: $name ?? $command,
            count: $count,
            user: $user,
            group: $group,
            onStart: $this->onStart(...),
        );
    }

    private function onStart(): void
    {
        $kernel = $this->kernelFactory->createKernel();
        $kernel->boot();
        $kernel->getContainer()->get('phpstreamserver.worker_configurator')->configure($this);
        /** @var Application $application */
        $application = $kernel->getContainer()->get('phpstreamserver.application');

        /** @psalm-suppress RiskyTruthyFalsyComparison */
        if ($application->has(\strstr($this->command, ' ', true) ?: $this->command)) {
            $this->runSymfonyCommand($application, $this->command);
        } else {
            unset($application, $kernel);
            $this->runExternalCommand($this->command);
        }
    }

    private function runSymfonyCommand(Application $application, string $command): void
    {
        $this->detach();
        $input = new StringInput($command);
        $input->setInteractive(false);
        $output = new NullOutput();
        $exitCode = $application->run($input, $output);
        exit($exitCode);
    }

    private function runExternalCommand(string $command): void
    {
        $this->detach();
        $envVars = [...\getenv(), ...$_ENV];
        \str_ends_with($command, '.sh')
            ? \pcntl_exec($command, [], $envVars)
            : \pcntl_exec('/bin/sh', ['-c', $command], $envVars)
        ;
        exit(0);
    }
}
