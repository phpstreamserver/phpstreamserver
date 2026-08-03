<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\ConsoleCommand;

use PHPStreamServer\Core\ConsoleCommand\StartCommand as BaseStartCommand;

final class StartCommand extends BaseStartCommand
{
    public function __construct()
    {
    }

    public function configure(): void
    {
        parent::configure();
        $this->addOptionDefinition('env', 'e', 'Set the environment name');
        $this->addOptionDefinition('no-debug', null, 'Disable debug mode');
    }
}
