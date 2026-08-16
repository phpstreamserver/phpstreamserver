<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use Revolt\EventLoop;

/**
 * @internal
 */
final class PollingFileWatcher extends AbstractFileWatcher
{
    public static float $pollingInterval = 2.0;

    private string $repeatCallbackId = '';
    private string $snapshotHash = '';
    private string $opcacheSnapshotHash = '';

    protected static function getReloadDelay(): float
    {
        return 0.0;
    }

    public function start(): void
    {
        [$this->snapshotHash, $this->opcacheSnapshotHash] = $this->createSnapshotHashes();

        EventLoop::defer($this->poll(...));
        $this->repeatCallbackId = EventLoop::repeat(self::$pollingInterval, $this->poll(...));
    }

    public function stop(): void
    {
        parent::stop();
        EventLoop::cancel($this->repeatCallbackId);
    }

    private function poll(): void
    {
        [$snapshotHash, $opcacheSnapshotHash] = $this->createSnapshotHashes();

        if ($snapshotHash === $this->snapshotHash) {
            return;
        }

        $invalidateOpcache = $opcacheSnapshotHash !== $this->opcacheSnapshotHash;
        $this->snapshotHash = $snapshotHash;
        $this->opcacheSnapshotHash = $opcacheSnapshotHash;
        $this->scheduleReload($invalidateOpcache);
    }

    /**
     * @return array{string, string}
     */
    private function createSnapshotHashes(): array
    {
        \clearstatcache();

        $context = \hash_init('xxh128');
        $opcacheContext = \hash_init('xxh128');

        foreach ($this->rules as $rule) {
            if (!\is_dir($rule->sourceDir)) {
                continue;
            }

            if ($rule->recursive) {
                $dirIterator = new \RecursiveDirectoryIterator($rule->sourceDir, \FilesystemIterator::SKIP_DOTS);
                $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);
            } else {
                $iterator = new \FilesystemIterator($rule->sourceDir, \FilesystemIterator::SKIP_DOTS);
            }

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $path = $file->getPathname();
                if (!$file->isFile() || !$this->isPatternMatches($rule, $path) || false === $stat = \stat($path)) {
                    continue;
                }

                $signature = $path . $stat['mtime'] . $stat['size'] . $stat['ino'];
                \hash_update($context, $signature);

                if ($rule->invalidateOpcache) {
                    \hash_update($opcacheContext, $signature);
                }
            }
        }

        return [\hash_final($context), \hash_final($opcacheContext)];
    }
}
