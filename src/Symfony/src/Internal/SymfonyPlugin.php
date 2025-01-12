<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Symfony\Event\HttpServerReloadEvent;
use PHPStreamServer\Symfony\Event\HttpServerStartEvent;
use PHPStreamServer\Symfony\Event\HttpServerStopEvent;
use PHPStreamServer\Symfony\Worker\SymfonyServerProcess;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class SymfonyPlugin extends Plugin
{
    public function __construct(private AppLoader $appLoader)
    {
    }

    public function addWorker(Process $worker): void
    {
        \assert($worker instanceof SymfonyServerProcess);

        $appLoader = $this->appLoader;

        $worker->onStart(priority: -1, onStart: static function (SymfonyServerProcess $worker) use ($appLoader): void {
            $kernel = $appLoader->createKernel();
            $kernel->boot();

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new HttpServerStartEvent($worker));
        });

        $worker->onStop(priority: 1000, onStop: static function (SymfonyServerProcess $worker): void {
            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new HttpServerStopEvent($worker));
        });

        $worker->onReload(priority: 1000, onReload: static function (SymfonyServerProcess $worker): void {
            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new HttpServerReloadEvent($worker));
        });
    }

    public function onReload(): void
    {
        $this->appLoader->loadEnv();
    }

    public function registerCommands(): iterable
    {
        return [new StartCommand($this->appLoader)];
    }
}
