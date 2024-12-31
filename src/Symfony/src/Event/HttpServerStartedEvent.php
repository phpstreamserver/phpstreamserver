<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Event;

use PHPStreamServer\Plugin\HttpServer\HttpServerProcess;
use Symfony\Contracts\EventDispatcher\Event;

final class HttpServerStartedEvent extends Event
{
    public function __construct(public readonly HttpServerProcess $worker)
    {
    }
}
