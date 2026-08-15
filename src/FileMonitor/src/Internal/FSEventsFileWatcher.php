<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use PHPStreamServer\Plugin\FileMonitor\Internal\FFIBindings\FSEvents;
use PHPStreamServer\Plugin\FileMonitor\WatchRule;
use Revolt\EventLoop;

/**
 * @internal
 */
final class FSEventsFileWatcher extends AbstractFileWatcher
{
    private const POLL_INTERVAL = 0.15;

    private FSEvents $fsevents;
    private string $repeatCallbackId = '';

    public function start(): void
    {
        $watchPaths = $this->getWatchPaths();
        if ($watchPaths === []) {
            return;
        }

        $this->fsevents = new FSEvents($watchPaths);
        $this->repeatCallbackId = EventLoop::repeat(self::POLL_INTERVAL, $this->poll(...));
    }

    public function stop(): void
    {
        parent::stop();

        if ($this->repeatCallbackId !== '') {
            EventLoop::cancel($this->repeatCallbackId);
            $this->repeatCallbackId = '';
        }

        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->fsevents)) {
            $this->fsevents->close();
            unset($this->fsevents);
        }
    }

    private function poll(): void
    {
        foreach ($this->fsevents->read() as $event) {
            $this->processEvent($event['path'], $event['flags']);
        }
    }

    private function processEvent(string $path, int $flags): void
    {
        $path = \rtrim($path, '/');
        $isDirChange = ($flags & (FSEvents::EVENT_FLAG_ITEM_IS_DIR | FSEvents::EVENT_FLAG_ITEM_IS_SYMLINK)) !== 0 && ($flags & (FSEvents::EVENT_FLAG_ITEM_REMOVED | FSEvents::EVENT_FLAG_ITEM_RENAMED)) !== 0;
        $isCreatedDir = ($flags & FSEvents::EVENT_FLAG_ITEM_CREATED) !== 0 && \is_dir($path);
        $isSourceDirChange = ($flags & (FSEvents::EVENT_FLAG_ITEM_REMOVED | FSEvents::EVENT_FLAG_ITEM_RENAMED | FSEvents::EVENT_FLAG_ROOT_CHANGED)) !== 0;

        foreach ($this->rules as $rule) {
            $sourceDir = \rtrim($rule->sourceDir, '/');

            if ($isSourceDirChange && $path === $sourceDir) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            if ($isCreatedDir && $path === $sourceDir && $this->containsMatchingFile($rule, $path)) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            if ($this->isPatternMatches($rule, $path)) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            if ($isDirChange && $rule->recursive && \str_starts_with($path, $sourceDir . '/')) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            if ($isCreatedDir && $rule->recursive && \str_starts_with($path, $sourceDir . '/') && $this->containsMatchingFile($rule, $path)) {
                $this->scheduleReload($rule->invalidateOpcache);
            }
        }
    }

    private function containsMatchingFile(WatchRule $rule, string $path): bool
    {
        if ($rule->recursive) {
            $directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($directory, flags: \RecursiveIteratorIterator::CATCH_GET_CHILD);
        } else {
            $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
        }

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $this->isPatternMatches($rule, $file->getPathname())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function getWatchPaths(): array
    {
        $paths = [];

        foreach ($this->rules as $rule) {
            $paths[$rule->sourceDir] = $rule->sourceDir;

            if (\is_link($rule->sourceDir)) {
                $parentDir = \dirname($rule->sourceDir);
                $paths[$parentDir] = $parentDir;
            }
        }

        return \array_values($paths);
    }
}
