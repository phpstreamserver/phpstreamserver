<?php

declare(strict_types=1);

namespace PHPStreamServer\Test\data\TestPlugin;

use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\WorkerInterface;

/**
 * @extends Plugin<WorkerInterface>
 */
final class TestPlugin extends Plugin
{
    public static function getDescription(): string
    {
        return 'Test plugin';
    }

    public function registerCommands(): array
    {
        return [
            new TestDispatchCommand(),
        ];
    }
}
