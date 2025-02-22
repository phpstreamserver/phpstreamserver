<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use PHPStreamServer\Plugin\Scheduler\Worker\PeriodicProcess;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

if (!\class_exists(PeriodicProcess::class)) {
    throw new \RuntimeException(\sprintf('You cannot use "%s\SymfonyCommandPeriodicProcess" as the "scheduler" package is not installed. Try running "composer require phpstreamserver/scheduler"', __NAMESPACE__));
}

final class SymfonyPeriodicProcess extends PeriodicProcess
{
    public readonly string $command;
    public readonly string $commandWithoutArguments;

    /**
     * @param string $command Symfony console command name with optinal parameters
     */
    public function __construct(
        string $command,
        string $name = '',
        string $schedule = '1 minute',
        int $jitter = 0,
        string|null $user = null,
        string|null $group = null,
    ) {
        $this->command = $command;
        $this->commandWithoutArguments = \strstr($command, ' ', true) ?: $command;

        parent::__construct(
            name: $name ?: $this->commandWithoutArguments,
            schedule: $schedule,
            jitter: $jitter,
            user: $user,
            group: $group,
        );
    }

    public static function handleBy(): array
    {
        return [...parent::handleBy(), SymfonyPlugin::class];
    }
}
