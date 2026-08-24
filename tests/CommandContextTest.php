<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Console\CommandContext;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Worker\SupervisedWorker;
use PHPUnit\Framework\TestCase;

final class CommandContextTest extends TestCase
{
    public function testTakingRuntimeStateReleasesConsoleReferences(): void
    {
        $plugin = new class extends Plugin {};
        $worker = new SupervisedWorker();
        $pluginReference = \WeakReference::create($plugin);
        $workerReference = \WeakReference::create($worker);
        $context = new CommandContext(
            pidFile: '/tmp/test.pid',
            socketFile: '/tmp/test.sock',
            plugins: [$plugin],
            workers: [$worker],
            workerFactories: [],
        );
        unset($plugin, $worker);

        $runtimeState = $context->takeRuntimeState();

        self::assertSame([], $context->getPlugins());
        self::assertSame([], $context->getWorkers());
        self::assertSame([], $context->getWorkerFactories());
        self::assertCount(1, $runtimeState['plugins']);
        self::assertCount(1, $runtimeState['workers']);

        unset($runtimeState);
        \gc_collect_cycles();

        self::assertNull($pluginReference->get());
        self::assertNull($workerReference->get());
    }
}
