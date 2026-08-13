<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use Revolt\EventLoop;

/**
 * @internal
 */
final class InotifyFileWatcher extends AbstractFileWatcher
{
    /**
     * @var resource
     */
    private mixed $fd;

    private string $fdCallbackId = '';

    /**
     * @var array<int, string>
     */
    private array $pathByWd = [];

    public function start(): void
    {
        if (false === $fd = \inotify_init()) {
            throw new \RuntimeException('Unable to initialize inotify');
        }

        $this->fd = $fd;
        \stream_set_blocking($this->fd, false);

        $this->watchDir($this->sourceDir, $this->recursive);
        $this->fdCallbackId = EventLoop::onReadable($this->fd, fn(string $id, mixed $fd) => $this->onNotify($fd));
    }

    public function stop(): void
    {
        EventLoop::cancel($this->fdCallbackId);
        \fclose($this->fd);
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

            if ($this->isPatternMatches($this->pathByWd[$event['wd']] . '/' . $event['name'])) {
                $this->scheduleReload();
            }
        }
    }

    private function watchDir(string $path, bool $recursive): bool
    {
        if (!\is_dir($path)) {
            return false;
        }

        if (false === $wd = \inotify_add_watch($this->fd, $path, IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVED_FROM | IN_MOVED_TO)) {
            throw new \RuntimeException(\sprintf('Unable to watch directory "%s"', $path));
        }

        $this->pathByWd[$wd] = $path;

        if (!$recursive) {
            return false;
        }

        $matchingFileFound = false;
        $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                $this->watchDir($file->getPathname(), false);
                continue;
            }

            if ($this->isPatternMatches($file->getPathname())) {
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
}
