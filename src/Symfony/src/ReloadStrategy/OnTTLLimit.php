<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\ReloadStrategy;

use Luzrain\PHPStreamServer\ReloadStrategy\TTLReloadStrategy;
use Luzrain\PHPStreamServerBundle\Event\HttpServerStartEvent;

final class OnTTLLimit extends TTLReloadStrategy
{
    public function onServerStart(HttpServerStartEvent $event): void
    {
        $event->worker->addReloadStrategies($this);
    }
}
