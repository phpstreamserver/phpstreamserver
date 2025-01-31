<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Command;

use PHPStreamServer\Core\Command\StartCommand as BaseStartCommand;

final class StartCommand extends BaseStartCommand
{
    public function __construct()
    {
    }

    public function configure(): void
    {
        parent::configure();
        $this->options->addOptionDefinition('env', 'e', 'The environment name');
        $this->options->addOptionDefinition('no-debug', null, 'Switch off debug mode');
    }
}
