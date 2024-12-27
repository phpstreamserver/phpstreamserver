<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Worker;

use Luzrain\PHPStreamServer\WorkerProcess;
use Luzrain\PHPStreamServerBundle\FileMonitorWatcher\FileMonitorWatcher;

final class FileMonitorWorker extends WorkerProcess
{
    public function __construct(
        private array $sourceDir,
        private array $filePattern,
        private float $pollingInterval,
        string|null $user,
        string|null $group,
        private \Closure $reloadCallback,
    ) {
        parent::__construct(
            name: 'File monitor',
            user: $user,
            group: $group,
            reloadable: false,
            onStart: $this->onStart(...),
        );
    }

    private function onStart(): void
    {
        $fileMonitor = FileMonitorWatcher::create(
            $this->getLogger(),
            $this->sourceDir,
            $this->filePattern,
            $this->pollingInterval,
            $this->doReload(...),
        );
        $fileMonitor->start($this->getEventLoop());
    }

    /**
     * @psalm-suppress NoValue
     * @psalm-suppress RiskyTruthyFalsyComparison
     */
    private function doReload(): void
    {
        ($this->reloadCallback)();

        if (\function_exists('opcache_get_status') && $status = \opcache_get_status()) {
            foreach (\array_keys($status['scripts'] ?? []) as $file) {
                \opcache_invalidate($file, true);
            }
        }
    }
}
