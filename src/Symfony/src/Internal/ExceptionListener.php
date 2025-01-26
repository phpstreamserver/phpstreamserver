<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\ContainerInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * @internal
 */
final class ExceptionListener
{
    private \WeakMap $map;

    public function __construct(private readonly ContainerInterface $workerContainer)
    {
        $this->map = new \WeakMap();
    }

    public function onException(ExceptionEvent $event): void
    {
        $this->map[$event->getRequest()] = $event->getThrowable();
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $exception = $this->map[$event->getRequest()] ?? null;

        if ($exception === null) {
            return;
        }

        /** @var \Closure $reloadStrategyEmitter */
        $reloadStrategyEmitter = $this->workerContainer->getService('reload_strategy_emitter');
        $reloadStrategyEmitter($exception);
    }
}
