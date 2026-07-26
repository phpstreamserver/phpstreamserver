<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Event;

use PHPStreamServer\Core\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<void>
 */
final readonly class ProcessStartedEvent implements MessageInterface
{
    public function __construct(
        public int $id,
        public int $pid,
    ) {
    }
}
