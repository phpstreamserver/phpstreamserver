<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use PHPStreamServer\Plugin\FileMonitor\WatchRule;

final readonly class FileWatcherFactory
{
    /**
     * @param list<WatchRule> $rules
     * @param \Closure(bool): void $reloadCallback
     * @param class-string<AbstractFileWatcher>|null $watcherClass
     */
    public static function create(array $rules, \Closure $reloadCallback, string|null $watcherClass = null): AbstractFileWatcher
    {
        $watcherClass ??= match (PHP_OS_FAMILY) {
            'Linux' => InotifyFileWatcher::class,
            'Darwin' => FSEventsFileWatcher::class,
            default => PollingFileWatcher::class,
        };

        if (!\is_subclass_of($watcherClass, AbstractFileWatcher::class)) {
            throw new \RuntimeException(\sprintf('FileWatcher implementation "%s" does not exist', $watcherClass));
        }

        return new $watcherClass($rules, $reloadCallback);
    }
}
