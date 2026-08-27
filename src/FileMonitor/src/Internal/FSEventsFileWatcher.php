<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use PHPStreamServer\Plugin\FileMonitor\Internal\FFIBindings\FSEvents;
use Revolt\EventLoop;

/**
 * @internal
 */
final class FSEventsFileWatcher extends AbstractFileWatcher
{
    private const POLL_INTERVAL = 0.25;

    private FSEvents $fsevents;
    private string $repeatCallbackId = '';

    /**
     * @var array<string, string>
     */
    private array $realPathToSourceDir = [];

    protected static function getReloadDelay(): float
    {
        return 0.05;
    }

    public function start(): void
    {
        $watchPaths = $this->getWatchPaths();
        if ($watchPaths === []) {
            return;
        }

        $this->realPathToSourceDir = [];
        foreach ($this->rules as $rule) {
            $sourceDir = \rtrim($rule->sourceDir, '/');
            if (false !== $parent = \realpath(\dirname($sourceDir))) {
                $this->realPathToSourceDir[$parent . '/' . \basename($sourceDir)] = $sourceDir;
            }
            if (false !== $real = \realpath($sourceDir)) {
                $this->realPathToSourceDir[\rtrim($real, '/')] = $sourceDir;
            }
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
        foreach ($this->realPathToSourceDir as $realPath => $sourceDir) {
            if ($path === $realPath || \str_starts_with($path, $realPath . '/')) {
                $path = $sourceDir . \substr($path, \strlen($realPath));
                break;
            }
        }

        $isRemovedOrRenamed = ($flags & (FSEvents::EVENT_FLAG_ITEM_REMOVED | FSEvents::EVENT_FLAG_ITEM_RENAMED)) !== 0;
        $isDirOrSymlink = ($flags & (FSEvents::EVENT_FLAG_ITEM_IS_DIR | FSEvents::EVENT_FLAG_ITEM_IS_SYMLINK)) !== 0;
        $isSourceDirChange = $isRemovedOrRenamed || ($flags & FSEvents::EVENT_FLAG_ROOT_CHANGED) !== 0;

        foreach ($this->rules as $rule) {
            $sourceDir = \rtrim($rule->sourceDir, '/');

            if ($isSourceDirChange && $path === $sourceDir) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            if ($this->isPatternMatches($rule, $path)) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            if ($isDirOrSymlink && $isRemovedOrRenamed && $rule->recursive && \str_starts_with($path, $sourceDir . '/')) {
                $this->scheduleReload($rule->invalidateOpcache);
            }
        }
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
