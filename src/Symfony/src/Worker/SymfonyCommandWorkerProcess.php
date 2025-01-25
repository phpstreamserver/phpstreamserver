<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use PHPStreamServer\Core\WorkerProcess;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

final class SymfonyCommandWorkerProcess extends WorkerProcess
{
    public readonly string $command;
    public readonly string $commandWithoutArguments;

    /**
     * @param string $command Symfony console command name with optinal parameters
     */
    public function __construct(
        string $name = '',
        int $count = 1,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        string $command = '',
    ) {
        $this->command = $command;
        $this->commandWithoutArguments = \strstr($command, ' ', true) ?: $command;

        parent::__construct(name: $name, count: $count, reloadable: $reloadable, user: $user, group: $group);

        $this->onStart($this->startProcess(...));
    }

    private function startProcess(): void
    {
        $this->stop();
    }

    public static function handleBy(): array
    {
        return [...parent::handleBy(), SymfonyPlugin::class];
    }
}
