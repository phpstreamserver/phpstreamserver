<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Event;

use PHPStreamServer\Core\MessageBus\AuthorizedSources;
use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Core\MessageBus\MessageSource;

/**
 * @implements MessageInterface<void>
 */
#[AuthorizedSources(MessageSource::MASTER)]
final readonly class ProcessStartedEvent implements MessageInterface
{
    public function __construct(
        public int $id,
        public int $pid,
    ) {
    }
}
