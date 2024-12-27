<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\FileMonitorWatcher;

use Revolt\EventLoop\Driver;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class PollingMonitorWatcher extends FileMonitorWatcher
{
    private const TO_MANY_FILES_WARNING_LIMIT = 1000;

    private int $lastMTime = 0;
    private bool $toManyFiles = false;

    protected function __construct(private readonly float $pollingInterval)
    {
    }

    public function start(Driver $eventLoop): void
    {
        $this->lastMTime = \time();
        $eventLoop->repeat($this->pollingInterval, $this->checkFileSystemChanges(...));
        $this->logger->notice('Polling file monitoring can be inefficient if the project has many files. Install the php-inotify extension to increase performance.');
    }

    private function checkFileSystemChanges(): void
    {
        $filesCout = 0;

        foreach ($this->sourceDir as $dir) {
            $dirIterator = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isDir()) {
                    continue;
                }

                if (!$this->toManyFiles && ++$filesCout > self::TO_MANY_FILES_WARNING_LIMIT) {
                    $this->toManyFiles = true;
                    $this->logger->warning('There are too many files. This makes file monitoring very slow. Install php-inotify extension to increase performance.');
                }

                if (!$this->isPatternMatch($file->getFilename())) {
                    continue;
                }

                if ($file->getFileInfo()->getMTime() > $this->lastMTime) {
                    $this->lastMTime = $file->getFileInfo()->getMTime();
                    $this->reload();
                    return;
                }
            }
        }
    }
}
