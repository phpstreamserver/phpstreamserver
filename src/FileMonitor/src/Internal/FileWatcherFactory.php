<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

final readonly class FileWatcherFactory
{
    public static function create(string $glob, \Closure $reloadCallback): AbstractFileWatcher
    {
        if (PHP_OS_FAMILY === 'Linux' && \function_exists('inotify_init')) {
            return new InotifyFileWatcher($glob, $reloadCallback);
        }

        return new PollingFileWatcher($glob, $reloadCallback);
    }
}
