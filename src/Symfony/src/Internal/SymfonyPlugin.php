<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Symfony\Command\StartCommand;
use PHPStreamServer\Symfony\Event\ProcessReloadEvent;
use PHPStreamServer\Symfony\Event\ProcessStartEvent;
use PHPStreamServer\Symfony\Event\ProcessStopEvent;
use PHPStreamServer\Symfony\Worker\SymfonyHttpServerProcess;
use PHPStreamServer\Symfony\Worker\SymfonyPeriodicProcess;
use PHPStreamServer\Symfony\Worker\SymfonyWorkerProcess;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class SymfonyPlugin extends Plugin
{
    /**
     * @param AppLoader<KernelInterface> $appLoader
     */
    public function __construct(private readonly AppLoader $appLoader)
    {
    }

    public function addWorker(Process $worker): void
    {
        if ($worker instanceof SymfonyHttpServerProcess) {
            $this->initializeSymfonyServerProcess($worker);
        } elseif ($worker instanceof SymfonyPeriodicProcess) {
            $this->initializeSymfonyPeriodicProcess($worker);
        } elseif ($worker instanceof SymfonyWorkerProcess) {
            $this->initializeSymfonyWorkerProcess($worker);
        }
    }

    private function initializeSymfonyServerProcess(SymfonyHttpServerProcess $process): void
    {
        $appLoader = $this->appLoader;

        $process->onStart(priority: -1, onStart: static function (SymfonyHttpServerProcess $worker) use ($appLoader): void {
            $_SERVER['APP_RUNTIME_MODE'] = 'worker=1&web=1';
            $kernel = $appLoader->loadApp();
            $kernel->boot();

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessStartEvent($worker));
        });

        $process->onStop(priority: 1000, onStop: static function (SymfonyHttpServerProcess $worker): void {
            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessStopEvent($worker));
        });

        $process->onReload(priority: 1000, onReload: static function (SymfonyHttpServerProcess $worker): void {
            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessReloadEvent($worker));
        });
    }

    private function initializeSymfonyPeriodicProcess(SymfonyPeriodicProcess $process): void
    {
        $appLoader = $this->appLoader;

        $process->onStart(priority: -1, onStart: static function (SymfonyPeriodicProcess $worker) use ($appLoader): void {
            $kernel = $appLoader->loadApp();
            $kernel->boot();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            if (!$application->has($worker->commandWithoutArguments)) {
                $worker->logger->error(\sprintf('Command "%s" is not defined', $worker->commandWithoutArguments));
                $worker->setExitCode(1);
                return;
            }

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');

            $input = new StringInput($worker->command);
            $output = new NullOutput();

            $eventDispatcher->dispatch(new ProcessStartEvent($worker));
            $exitCode = $application->run($input, $output);
            $eventDispatcher->dispatch(new ProcessStopEvent($worker));
            $worker->setExitCode($exitCode);
        });
    }

    private function initializeSymfonyWorkerProcess(SymfonyWorkerProcess $process): void
    {
        $appLoader = $this->appLoader;

        $process->onStart(priority: -1, onStart: static function (SymfonyWorkerProcess $worker) use ($appLoader): void {
            $_SERVER['APP_RUNTIME_MODE'] = 'worker=1';
            $kernel = $appLoader->loadApp();
            $kernel->boot();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            if (!$application->has($worker->commandWithoutArguments)) {
                $worker->logger->error(\sprintf('Command "%s" is not defined', $worker->commandWithoutArguments));
                $worker->stop(1);
                return;
            }

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');

            $input = new StringInput($worker->command);
            $output = new NullOutput();

            $eventDispatcher->dispatch(new ProcessStartEvent($worker));
            $application->run($input, $output);
            $eventDispatcher->dispatch(new ProcessStopEvent($worker));
        });
    }

    public function onReload(): void
    {
        $this->appLoader->loadEnv();
    }

    public function registerCommands(): iterable
    {
        return [new StartCommand()];
    }
}
