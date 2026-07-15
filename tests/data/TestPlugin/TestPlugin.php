<?php

declare(strict_types=1);

namespace PHPStreamServer\Test\data\TestPlugin;

use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Process;

/**
 * @extends Plugin<Process>
 */
final class TestPlugin extends Plugin
{
    public function registerCommands(): array
    {
        return [
            new TestDispatchCommand(),
        ];
    }
}
