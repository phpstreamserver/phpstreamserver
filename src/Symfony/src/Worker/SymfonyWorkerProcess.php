<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Worker;

use PHPStreamServer\Core\Message\ProcessDetachedEvent;
use PHPStreamServer\Core\Worker\WorkerProcess;
use PHPStreamServer\Symfony\Internal\SymfonyPlugin;

final class SymfonyWorkerProcess extends WorkerProcess
{
    public readonly string $command;
    public readonly string $commandWithoutArguments;

    /**
     * @param string $command Symfony console command name with optinal parameters
     */
    public function __construct(
        string $command,
        int $count = 1,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
    ) {
        $this->command = $command;
        $this->commandWithoutArguments = \strstr($command, ' ', true) ?: $command;

        parent::__construct(name: $this->commandWithoutArguments, count: $count, reloadable: $reloadable, user: $user, group: $group);

        $this->onStart($this->startProcess(...), -2);
        $this->onStart(fn() => $this->stop());
    }

    private function startProcess(): void
    {
        $this->bus->dispatch(new ProcessDetachedEvent($this->pid))->await();
    }

    public static function handleBy(): array
    {
        return [...parent::handleBy(), SymfonyPlugin::class];
    }
}
