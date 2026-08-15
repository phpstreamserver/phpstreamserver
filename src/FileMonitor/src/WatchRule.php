<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor;

use Webmozart\Glob\Glob;

final readonly class WatchRule
{
    public string $sourceDir;
    public string $globRegex;
    public bool $recursive;

    /**
     * @param string $glob absolute glob of files to watch; use a globstar directory segment to watch files recursively
     * @param bool $invalidateOpcache whether to invalidate cached scripts on reload
     */
    public function __construct(string $glob, public bool $invalidateOpcache = false)
    {
        $this->sourceDir = Glob::getBasePath($glob);
        /** @psalm-suppress ArgumentTypeCoercion */
        $this->globRegex = Glob::toRegEx($glob);
        $this->recursive = \dirname($glob) !== $this->sourceDir;
    }
}
