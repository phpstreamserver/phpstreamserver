<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Event;

use PHPStreamServer\Core\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class WorkerStartEvent extends Event
{
    public function __construct(public readonly WorkerInterface $worker)
    {
    }
}
