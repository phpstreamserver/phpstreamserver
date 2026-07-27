<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Command;

use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Plugin\Scheduler\WorkerInfo;

/**
 * Retrieves metadata for workers registered with the scheduler.
 *
 * @implements MessageInterface<array<WorkerInfo>>
 */
final class GetWorkersCommand implements MessageInterface
{
}
