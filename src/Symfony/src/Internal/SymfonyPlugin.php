<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Symfony\Event\HttpServerStartedEvent;
use PHPStreamServer\Symfony\Worker\SymfonyServerProcess;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class SymfonyPlugin extends Plugin
{
    public function __construct(private KernelLoader $kernelLoader)
    {
    }

    public function addWorker(Process $worker): void
    {
        \assert($worker instanceof SymfonyServerProcess);

        $kernelLoader = $this->kernelLoader;

        $worker->onStart(priority: -1, onStart: static function (SymfonyServerProcess $worker) use ($kernelLoader): void {
            $kernel = $kernelLoader->createKernel();
            $kernel->boot();

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new HttpServerStartedEvent($worker));
        });
    }
}
