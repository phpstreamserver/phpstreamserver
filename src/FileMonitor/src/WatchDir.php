<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor;

final readonly class WatchDir
{
    public function __construct(
        public string $sourceDir,
        public array $filePattern = ['*'],
        public bool $recursive = false,
        public bool $invalidateOpcache = false,
    ) {
    }
}
