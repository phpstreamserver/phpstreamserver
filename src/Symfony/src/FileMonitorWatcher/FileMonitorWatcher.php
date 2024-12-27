<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\FileMonitorWatcher;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;

/**
 * @psalm-suppress UndefinedPropertyAssignment
 * @psalm-suppress InaccessibleProperty
 */
abstract class FileMonitorWatcher
{
    protected readonly LoggerInterface $logger;
    protected readonly array $sourceDir;
    private readonly array $filePattern;
    private readonly \Closure $reloadCallback;

    public static function create(
        LoggerInterface $logger,
        /** @var list<string> $sourceDir */
        array $sourceDir,
        /** @var list<string> $filePattern */
        array $filePattern,
        float $pollingInterval,
        \Closure $reloadCallback,
    ): self {
        $watcher = \extension_loaded('inotify') ? new InotifyMonitorWatcher() : new PollingMonitorWatcher($pollingInterval);
        $watcher->logger = $logger;
        $watcher->sourceDir = \array_filter($sourceDir, \is_dir(...));
        $watcher->filePattern = $filePattern;
        $watcher->reloadCallback = $reloadCallback;

        return $watcher;
    }

    final protected function isPatternMatch(string $filename): bool
    {
        foreach ($this->filePattern as $pattern) {
            if (\fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    final protected function reload(): void
    {
        ($this->reloadCallback)();
    }

    abstract public function start(Driver $eventLoop): void;
}
