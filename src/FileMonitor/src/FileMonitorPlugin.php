<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor;

use PHPStreamServer\Core\Command\ReloadServerCommand;
use PHPStreamServer\Core\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\WorkerInterface;
use PHPStreamServer\Plugin\FileMonitor\Internal\AbstractFileWatcher;
use PHPStreamServer\Plugin\FileMonitor\Internal\FileWatcherFactory;
use PHPStreamServer\Plugin\FileMonitor\Internal\PollingFileWatcher;
use Revolt\EventLoop;

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

        try {
            $this->fileWatcher = FileWatcherFactory::create($this->watchRules, $reloadCallback);
            $this->fileWatcher->start();
        } catch (\Throwable $e) {
            $logger = &$this->masterContainer->getService(LoggerInterface::class);
            $initialWatcherClass = $this->fileWatcher::class;
            $this->fileWatcher = FileWatcherFactory::create($this->watchRules, $reloadCallback, PollingFileWatcher::class);
            $this->fileWatcher->start();
            EventLoop::defer(static function () use ($initialWatcherClass, $e, &$logger): void {
                $logger->error(\sprintf('Failed to start %s, falling back to %s: %s', $initialWatcherClass, PollingFileWatcher::class, $e->getMessage()));
            });
        }
    }

    public function onStop(): void
    {
        $this->fileWatcher?->stop();
        $this->fileWatcher = null;
    }
}
