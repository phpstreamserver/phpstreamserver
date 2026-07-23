<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Plugin\Scheduler\WorkerInfo;

/**
 * @implements MessageInterface<array<WorkerInfo>>
 */
final class GetWorkersCommand implements MessageInterface
{
}
