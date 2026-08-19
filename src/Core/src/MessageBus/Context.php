<?php

declare(strict_types=1);

namespace PHPStreamServer\Core\MessageBus;

final readonly class Context
{
    public const SOURCE_MASTER = 0; // Master process
    public const SOURCE_CHILD = 1; // Worker process
    public const SOURCE_EXTERNAL = 2; // External proocess

    /**
     * @param self::SOURCE_* $source
     */
    public function __construct(
        public int $source,
        public int $pid,
        public int $uid,
        public int $gid,
        public string $user,
        public string $group,
    ) {
    }
}
