<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\FileMonitorWatcher;

use Revolt\EventLoop\Driver;

/**
 * @psalm-suppress UndefinedConstant
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class InotifyMonitorWatcher extends FileMonitorWatcher
{
    private const REBOOT_DELAY = 0.3;

    /** @var resource */
    private mixed $fd;
    /** @var array<int, string> */
    private array $pathByWd = [];
    private \Closure|null $delayedRebootCallback = null;

    protected function __construct()
    {
    }

    public function start(Driver $eventLoop): void
    {
        $this->fd = \inotify_init();
        \stream_set_blocking($this->fd, false);

        foreach ($this->sourceDir as $dir) {
            $dirIterator = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

            $this->watchDir($dir);
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isDir()) {
                    $this->watchDir($file->getPathname());
                }
            }
        }

        $eventLoop->onReadable($this->fd, fn(string $id, mixed $fd) => $this->onNotify($eventLoop, $fd));
    }

    /**
     * @param resource $inotifyFd
     */
    private function onNotify(Driver $eventLoop, mixed $inotifyFd): void
    {
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        $events = \inotify_read($inotifyFd) ?: [];

        if ($this->delayedRebootCallback !== null) {
            return;
        }

        foreach($events as $event) {
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
                $this->reload();
            };

            $eventLoop->delay(self::REBOOT_DELAY, $this->delayedRebootCallback);

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
}
