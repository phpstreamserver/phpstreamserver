<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor;

use PHPStreamServer\Core\Message\ReloadServerCommand;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Plugin\FileMonitor\Internal\InotifyMonitorWatcher;

final class FileMonitorPlugin extends Plugin
{
    private MessageBusInterface $messageBus;
    private array $watchDirs;

    public function __construct(WatchDir ...$watch)
    {
        $this->watchDirs = $watch;
    }

    public function onStart(): void
    {
        $this->messageBus = $this->masterContainer->getService(MessageBusInterface::class);

        foreach ($this->watchDirs as $watchDir) {
            $fileMonitor = new InotifyMonitorWatcher(
                sourceDir: $watchDir->sourceDir,
                filePattern: $watchDir->filePattern,
                reloadCallback: $watchDir->invalidateOpcache
                    ? $this->triggerReloadWithOpcacheReset(...)
                    : $this->triggerReloadWithoutOpcacheReset(...),
            );

            $fileMonitor->start();
        }
    }

    private function triggerReloadWithoutOpcacheReset(): void
    {
        $this->messageBus->dispatch(new ReloadServerCommand());
    }

    /**
     * @psalm-suppress NoValue
     */
    private function triggerReloadWithOpcacheReset(): void
    {
        $this->messageBus->dispatch(new ReloadServerCommand());

        if (\function_exists('opcache_get_status') && false !== $status = \opcache_get_status()) {
            foreach (\array_keys($status['scripts'] ?? []) as $file) {
                \opcache_invalidate($file, true);
            }
        }
    }
}
