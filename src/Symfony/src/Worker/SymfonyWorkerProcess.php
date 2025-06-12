<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use PHPStreamServer\Core\ReloadStrategy\ReloadStrategy;
use PHPStreamServer\Core\Worker\WorkerProcess;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

final class SymfonyWorkerProcess extends WorkerProcess
{
    public readonly string $command;
    public readonly string $commandWithoutArguments;

    /**
     * @param string $command Symfony console command name with optinal parameters
     * @param array<ReloadStrategy> $reloadStrategies
     */
    public function __construct(
        string $command,
        string $name = '',
        int $count = 1,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        array $reloadStrategies = [],
    ) {
        $this->command = $command;
        $this->commandWithoutArguments = \strstr($command, ' ', true) ?: $command;

        parent::__construct(
            name: $name ?: $this->commandWithoutArguments,
            count: $count,
            reloadable: $reloadable,
            user: $user,
            group: $group,
            reloadStrategies: $reloadStrategies,
        );
    }

    public static function handleBy(): array
    {
        return [...parent::handleBy(), SymfonyPlugin::class];
    }
}
