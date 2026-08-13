<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use Revolt\EventLoop;

/**
 * @internal
 */
final class PollingFileWatcher extends AbstractFileWatcher
{
    private const POLLING_INTERVAL = 2.0;

    private string $repeatCallbackId = '';
    private string $snapshotHash = '';

    public function start(): void
    {
        $this->snapshotHash = $this->createSnapshotHash();
        $this->repeatCallbackId = EventLoop::repeat(self::POLLING_INTERVAL, $this->poll(...));
    }

    public function stop(): void
    {
        EventLoop::cancel($this->repeatCallbackId);
    }

    private function poll(): void
    {
        $snapshotHash = $this->createSnapshotHash();

        if ($snapshotHash !== $this->snapshotHash) {
            $this->snapshotHash = $snapshotHash;
            $this->scheduleReload();
        }
    }

    private function createSnapshotHash(): string
    {
        \clearstatcache();

        if (!\is_dir($this->sourceDir)) {
            return \hash('xxh128', '');
        }

        if ($this->recursive) {
            $dirIterator = new \RecursiveDirectoryIterator($this->sourceDir, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);
        } else {
            $iterator = new \FilesystemIterator($this->sourceDir, \FilesystemIterator::SKIP_DOTS);
        }

        $files = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $this->isPatternMatches($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }

        \sort($files);

        $context = \hash_init('xxh128');
        foreach ($files as $file) {
            if (false === $stat = \stat($file)) {
                continue;
            }

            \hash_update($context, $file . $stat['mtime'] . $stat['size'] . $stat['ino']);
        }

        return \hash_final($context);
    }
}
