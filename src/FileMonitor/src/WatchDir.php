<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor;

final readonly class WatchDir
{
    /**
     * @param string $glob absolute glob of files to watch; use a globstar directory segment to watch files recursively
     * @param bool $invalidateOpcache whether to invalidate cached scripts on reload
     */
    public function __construct(
        public string $glob,
        public bool $invalidateOpcache = false,
    ) {
    }
}
