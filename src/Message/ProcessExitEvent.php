<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * @implements Message<void>
 */
final readonly class ProcessExitEvent implements Message
{
    public function __construct(
        public int $pid,
        public int $exitCode,
    ) {
    }
}
