<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\ReloadStrategy;

use Luzrain\PHPStreamServer\ReloadStrategy\MaxRequestsReloadStrategy;
use Luzrain\PHPStreamServerBundle\Event\HttpServerStartEvent;

final class OnRequestsLimit extends MaxRequestsReloadStrategy
{
    public function onServerStart(HttpServerStartEvent $event): void
    {
        $event->worker->addReloadStrategies($this);
    }
}
