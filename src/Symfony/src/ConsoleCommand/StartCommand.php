<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\ConsoleCommand;

use PHPStreamServer\Core\Console\OptionDefinition;
use PHPStreamServer\Core\ConsoleCommand\StartCommand as BaseStartCommand;

final class StartCommand extends BaseStartCommand
{
    public function __construct()
    {
    }

    public function getOptionDefinitions(): array
    {
        return [
            ...parent::getOptionDefinitions(),
            new OptionDefinition('env', 'e', 'Set the environment name', requiresValue: true),
            new OptionDefinition('no-debug', null, 'Disable debug mode'),
        ];
    }
}
