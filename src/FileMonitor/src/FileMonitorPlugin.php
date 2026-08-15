<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor;

use PHPStreamServer\Core\Command\ReloadServerCommand;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\WorkerInterface;
use PHPStreamServer\Plugin\FileMonitor\Internal\AbstractFileWatcher;
use PHPStreamServer\Plugin\FileMonitor\Internal\FileWatcherFactory;

/**
 * @extends Plugin<WorkerInterface>
 */
final class FileMonitorPlugin extends Plugin
{
    private MessageBusInterface $messageBus;

    /**
     * @var list<WatchRule>
     */
    private array $watchRules;

    private AbstractFileWatcher|null $fileWatcher = null;

    public function __construct(WatchRule ...$watchRules)
    {
        $this->watchRules = \array_values($watchRules);
    }

    public function onStart(): void
    {
        $this->messageBus = $this->masterContainer->getService(MessageBusInterface::class);

        if ($this->watchRules === []) {
            return;
        }

        $messageBus = $this->messageBus;
        $reloadCallback = static function (bool $invalidateOpcache) use ($messageBus): void {
            $messageBus->dispatch(new ReloadServerCommand($invalidateOpcache));
        };

        $this->fileWatcher = FileWatcherFactory::create($this->watchRules, $reloadCallback);
        $this->fileWatcher->start();
    }

    public function onStop(): void
    {
        $this->fileWatcher?->stop();
        $this->fileWatcher = null;
    }
}
