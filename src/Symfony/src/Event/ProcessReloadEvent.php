<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Event;

use PHPStreamServer\Core\Process;
use Symfony\Contracts\EventDispatcher\Event;

final class ProcessReloadEvent extends Event
{
    public function __construct(public readonly Process $worker)
    {
    }
}
