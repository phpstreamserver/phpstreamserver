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
            if (($event['flags'] & (FSEvents::EVENT_FLAG_ROOT_CHANGED | FSEvents::EVENT_FLAG_MUST_SCAN_SUB_DIRS)) !== 0) {
                foreach ($this->rules as $rule) {
                    $this->scheduleReload($rule->invalidateOpcache);
                }
                continue;
            }

            $this->processEvent($event['path'], $event['flags']);
        }
    }

    private function processEvent(string $path, int $flags): void
    {
        $path = \rtrim($path, '/');
        $isStructuralChange = ($flags & (FSEvents::EVENT_FLAG_ITEM_CREATED | FSEvents::EVENT_FLAG_ITEM_REMOVED | FSEvents::EVENT_FLAG_ITEM_RENAMED)) !== 0;
        $isChangedDirectory = ($flags & FSEvents::EVENT_FLAG_ITEM_IS_DIR) !== 0 && $isStructuralChange;

        foreach ($this->rules as $rule) {
            if ($isStructuralChange && $path === \rtrim($rule->sourceDir, '/')) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            if ($this->isPatternMatches($rule, $path)) {
                $this->scheduleReload($rule->invalidateOpcache);
                continue;
            }

            $sourceDir = \rtrim($rule->sourceDir, '/');
            if (!$isChangedDirectory || !$rule->recursive || ($path !== $sourceDir && !\str_starts_with($path, $sourceDir . '/'))) {
                continue;
            }

            $this->scheduleReload($rule->invalidateOpcache);
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
        }

        return \array_values($paths);
    }
}
