<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use PHPStreamServer\Core\ReloadStrategy\ReloadStrategy;
use PHPStreamServer\Core\Worker\SupervisedWorker;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

final class SymfonySupervisedCommandWorker extends SupervisedWorker
{
    public readonly string $commandInput;
    public readonly string $commandName;

    /**
     * @param string $command Symfony Console command name with optional arguments and options
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
        $this->commandInput = $command;
        $this->commandName = \strstr($command, ' ', true) ?: $command;

        parent::__construct(
            name: $name ?: $this->commandName,
            count: $count,
            reloadable: $reloadable,
            user: $user,
            group: $group,
            reloadStrategies: $reloadStrategies,
        );
    }

    public static function handledBy(): array
    {
        return [...parent::handledBy(), SymfonyPlugin::class];
    }
}
