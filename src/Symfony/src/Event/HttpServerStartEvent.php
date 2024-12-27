<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Event;

use Luzrain\PHPStreamServer\WorkerProcess;
use Symfony\Contracts\EventDispatcher\Event;

final class HttpServerStartEvent extends Event
{
    public function __construct(public readonly WorkerProcess $worker)
    {
    }
}
