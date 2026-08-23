<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Command;

use PHPStreamServer\Core\MessageBus\AuthorizedSources;
use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Core\MessageBus\MessageSource;
use PHPStreamServer\Plugin\Scheduler\WorkerInfo;

/**
 * Retrieves metadata for workers registered with the scheduler.
 *
 * @implements MessageInterface<array<WorkerInfo>>
 */
#[AuthorizedSources(MessageSource::MASTER, MessageSource::MANAGER)]
final class GetWorkersCommand implements MessageInterface
{
}
