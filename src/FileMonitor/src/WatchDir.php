<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor;

final readonly class WatchDir
{
    /**
     * @param string $sourceDir directory to watch
     * @param array<string> $filePatterns glob patterns for filenames to watch
     * @param bool $recursive whether to watch subdirectories
     * @param bool $invalidateOpcache whether to invalidate cached scripts on reload
     */
    public function __construct(
        public string $sourceDir,
        public array $filePatterns = ['*'],
        public bool $recursive = false,
        public bool $invalidateOpcache = false,
    ) {
    }
}
