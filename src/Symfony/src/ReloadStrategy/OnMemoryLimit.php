<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\ReloadStrategy;

use Luzrain\PHPStreamServer\ReloadStrategy\MaxMemoryReloadStrategy;
use Luzrain\PHPStreamServerBundle\Event\HttpServerStartEvent;

final class OnMemoryLimit extends MaxMemoryReloadStrategy
{
    public function onServerStart(HttpServerStartEvent $event): void
    {
        $event->worker->addReloadStrategies($this);
    }
}
