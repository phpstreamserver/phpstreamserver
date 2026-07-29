<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use Revolt\EventLoop;

/**
 * @internal
 */
final class InotifyMonitorWatcher
{
    private const RELOAD_DELAY = 0.25;

    /**
     * @var resource
     */
    private mixed $fd;

    /**
     * @var array<int, string>
     */
    private array $pathByWd = [];

    private \Closure $reloadCallback;
    private string $delayedReloadCallbackId = '';

    public function __construct(
        private readonly string $sourceDir,
        private readonly array $filePatterns,
        private readonly bool $recursive,
        \Closure $reloadCallback,
    ) {
        $delayedReloadCallbackId = &$this->delayedReloadCallbackId;
        $this->reloadCallback = static function () use ($reloadCallback, &$delayedReloadCallbackId): void {
            $delayedReloadCallbackId = '';
            ($reloadCallback)();
        };
    }

    public function start(): void
    {
        $this->fd = \inotify_init();
        \stream_set_blocking($this->fd, false);

        $this->watchDir($this->sourceDir, $this->recursive);

        EventLoop::onReadable($this->fd, fn(string $id, mixed $fd) => $this->onNotify($fd));
    }

    /**
     * @param resource $inotifyFd
     */
    private function onNotify(mixed $inotifyFd): void
    {
        if (false === $events = \inotify_read($inotifyFd)) {
            $events = [];
        }

        foreach ($events as $event) {
            $isIgnored = ($event['mask'] & IN_IGNORED) !== 0;
            $isDirectory = ($event['mask'] & IN_ISDIR) !== 0;
            $isCreatedDirectory = $isDirectory && ($event['mask'] & IN_CREATE) !== 0;
            $isMovedFromDirectory = $isDirectory && ($event['mask'] & IN_MOVED_FROM) !== 0;
            $isMovedToDirectory = $isDirectory && ($event['mask'] & IN_MOVED_TO) !== 0;

            if ($isIgnored) {
                unset($this->pathByWd[$event['wd']]);
                continue;
            }

            if (!isset($this->pathByWd[$event['wd']])) {
                continue;
            }

            if ($isMovedFromDirectory) {
                $this->unwatchDir($this->pathByWd[$event['wd']] . '/' . $event['name']);

                if ($this->recursive) {
                    $this->scheduleReload();
                }
            }

            if ($isCreatedDirectory || $isMovedToDirectory) {
                if ($this->recursive && $this->watchDir($this->pathByWd[$event['wd']] . '/' . $event['name'], true)) {
                    $this->scheduleReload();
                }

                if ($isCreatedDirectory) {
                    continue;
                }
            }

            if ($this->isPatternMatches($event['name'])) {
                $this->scheduleReload();
            }
        }
    }

    private function scheduleReload(): void
    {
        if ($this->delayedReloadCallbackId === '') {
            $this->delayedReloadCallbackId = EventLoop::delay(self::RELOAD_DELAY, $this->reloadCallback);
        }
    }

    private function watchDir(string $path, bool $recursive): bool
    {
        if (!\is_dir($path)) {
            return false;
        }

        $wd = \inotify_add_watch($this->fd, $path, IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVED_FROM | IN_MOVED_TO);
        $this->pathByWd[$wd] = $path;

        if (!$recursive) {
            return false;
        }

        $matchingFileFound = false;
        $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                $this->watchDir($file->getPathname(), false);
                continue;
            }

            if ($this->isPatternMatches($file->getFilename())) {
                $matchingFileFound = true;
            }
        }

        return $matchingFileFound;
    }

    private function unwatchDir(string $path): void
    {
        foreach ($this->pathByWd as $wd => $watchedPath) {
            if ($watchedPath !== $path && !\str_starts_with($watchedPath, $path . '/')) {
                continue;
            }

            \inotify_rm_watch($this->fd, $wd);
            unset($this->pathByWd[$wd]);
        }
    }

    private function isPatternMatches(string $filename): bool
    {
        foreach ($this->filePatterns as $pattern) {
            if (\fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }
}
