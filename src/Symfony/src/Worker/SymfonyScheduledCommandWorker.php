<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use PHPStreamServer\Plugin\Scheduler\Worker\ScheduledWorker;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

if (!\class_exists(ScheduledWorker::class)) {
    throw new \RuntimeException(\sprintf('You cannot use "%s\SymfonyScheduledCommandWorker" because the "scheduler" package is not installed. Try running "composer require phpstreamserver/scheduler"', __NAMESPACE__));
}

final class SymfonyScheduledCommandWorker extends ScheduledWorker
{
    public readonly string $commandInput;
    public readonly string $commandName;

    /**
     * @param string $command Symfony Console command name with optional arguments and options
     */
    public function __construct(
        string $command,
        string $name = '',
        string $schedule = '1 minute',
        int $jitter = 0,
        string|null $user = null,
        string|null $group = null,
    ) {
        $this->commandInput = $command;
        $this->commandName = \strstr($command, ' ', true) ?: $command;

        parent::__construct(
            name: $name ?: $this->commandName,
            schedule: $schedule,
            jitter: $jitter,
            user: $user,
            group: $group,
        );
    }

    public static function handledBy(): array
    {
        return [...parent::handledBy(), SymfonyPlugin::class];
    }
}
