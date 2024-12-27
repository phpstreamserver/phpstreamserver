<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\ReloadStrategy;

use Luzrain\PHPStreamServer\ReloadStrategy\EachRequestReloadStrategy;
use Luzrain\PHPStreamServerBundle\Event\HttpServerStartEvent;

final class OnEachRequest extends EachRequestReloadStrategy
{
    public function onServerStart(HttpServerStartEvent $event): void
    {
        $event->worker->addReloadStrategies($this);
    }
}
