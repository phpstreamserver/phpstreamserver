<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use PHPStreamServer\Plugin\FileMonitor\WatchRule;

final readonly class FileWatcherFactory
{
    /**
     * @param list<WatchRule> $rules
     * @param \Closure(bool): void $reloadCallback
     */
    public static function create(array $rules, \Closure $reloadCallback): AbstractFileWatcher
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return new InotifyFileWatcher($rules, $reloadCallback, 0.15);
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            return new FSEventsFileWatcher($rules, $reloadCallback, 0.05);
        }

        return new PollingFileWatcher($rules, $reloadCallback, 0.05);
    }
}
