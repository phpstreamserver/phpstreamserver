<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use Revolt\EventLoop;

/**
 * @internal
 */
final class InotifyMonitorWatcher
{
    private const REBOOT_DELAY = 0.3;

    /** @var resource */
    private mixed $fd;
    /** @var array<int, string> */
    private array $pathByWd = [];
    private \Closure|null $delayedRebootCallback = null;

    public function __construct(
        private readonly string $sourceDir,
        private readonly array $filePattern,
        private readonly bool $recursive,
        private readonly \Closure $reloadCallback,
    ) {
    }

    public function start(): void
    {
        $this->fd = \inotify_init();
        \stream_set_blocking($this->fd, false);

        $this->watchDir($this->sourceDir);

        if ($this->recursive) {
            $dirIterator = new \RecursiveDirectoryIterator($this->sourceDir, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isDir()) {
                    continue;
                }

                $this->watchDir($file->getPathname());
            }
        }

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

        if ($this->delayedRebootCallback !== null) {
            return;
        }

        foreach ($events as $event) {
            if ($this->isFlagSet($event['mask'], IN_IGNORED)) {
                unset($this->pathByWd[$event['wd']]);
                continue;
            }

            if ($this->isFlagSet($event['mask'], IN_CREATE | IN_ISDIR)) {
                $this->watchDir($this->pathByWd[$event['wd']] . '/' . $event['name']);
                continue;
            }

            if (!$this->isPatternMatch($event['name'])) {
                continue;
            }

            $this->delayedRebootCallback = function (): void {
                $this->delayedRebootCallback = null;
                ($this->reloadCallback)();
            };

            EventLoop::delay(self::REBOOT_DELAY, $this->delayedRebootCallback);

            return;
        }
    }

    private function watchDir(string $path): void
    {
        $wd = \inotify_add_watch($this->fd, $path, IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVED_TO);
        $this->pathByWd[$wd] = $path;
    }

    private function isFlagSet(int $check, int $flag): bool
    {
        return ($check & $flag) === $flag;
    }

    private function isPatternMatch(string $filename): bool
    {
        foreach ($this->filePattern as $pattern) {
            if (\fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }
}
