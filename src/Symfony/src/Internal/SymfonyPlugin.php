<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\Internal\ErrorHandler;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\WorkerInterface;
use PHPStreamServer\Symfony\Command\StartCommand;
use PHPStreamServer\Symfony\Event\WorkerReloadEvent;
use PHPStreamServer\Symfony\Event\WorkerStartEvent;
use PHPStreamServer\Symfony\Event\WorkerStopEvent;
use PHPStreamServer\Symfony\Worker\SymfonyHttpServerWorker;
use PHPStreamServer\Symfony\Worker\SymfonyScheduledCommandWorker;
use PHPStreamServer\Symfony\Worker\SymfonySupervisedCommandWorker;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 * @extends Plugin<SymfonyHttpServerWorker|SymfonyScheduledCommandWorker|SymfonySupervisedCommandWorker>
 */
final class SymfonyPlugin extends Plugin
{
    /**
     * @param AppLoader<KernelInterface> $appLoader
     */
    public function __construct(private readonly AppLoader $appLoader)
    {
    }

    public function registerWorker(WorkerInterface $worker): void
    {
        if ($worker instanceof SymfonyHttpServerWorker) {
            $this->initializeSymfonyHttpServerWorker($worker);
        } elseif ($worker instanceof SymfonyScheduledCommandWorker) {
            $this->initializeSymfonyScheduledCommandWorker($worker);
        } elseif ($worker instanceof SymfonySupervisedCommandWorker) {
            $this->initializeSymfonySupervisedCommandWorker($worker);
        }
    }

    private function initializeSymfonyHttpServerWorker(SymfonyHttpServerWorker $worker): void
    {
        $appLoader = $this->appLoader;
        $workerContainer = $this->workerContainer;
        $isBooted = false;

        $worker->onStart(priority: -1, onStart: static function (SymfonyHttpServerWorker $worker) use ($appLoader, $workerContainer, &$isBooted): void {
            $_SERVER['APP_RUNTIME_MODE'] = 'worker=1&web=1';

            try {
                $kernel = $appLoader->loadApp();
                $kernel->boot();

                /** @var EventDispatcherInterface $eventDispatcher */
                $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
                $eventDispatcher->dispatch(new WorkerStartEvent($worker));
                $isBooted = true;
            } catch (\Throwable $e) {
                ErrorHandler::handleException($e);
                $workerContainer->setService('request_handler', static fn(): never => throw $e);
            }
        });

        $worker->onStop(priority: 1000, onStop: static function (SymfonyHttpServerWorker $worker) use (&$isBooted): void {
            if (!$isBooted) {
                return;
            }

            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new WorkerStopEvent($worker));
        });

        $worker->onReload(priority: 1000, onReload: static function (SymfonyHttpServerWorker $worker) use (&$isBooted): void {
            if (!$isBooted) {
                return;
            }

            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new WorkerReloadEvent($worker));
        });
    }

    private function initializeSymfonyScheduledCommandWorker(SymfonyScheduledCommandWorker $worker): void
    {
        $appLoader = $this->appLoader;

        $worker->onStart(priority: -1, onStart: static function (SymfonyScheduledCommandWorker $worker) use ($appLoader): void {
            $kernel = $appLoader->loadApp();
            $kernel->boot();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            if (!$application->has($worker->commandName)) {
                $worker->logger->error(\sprintf('Command "%s" is not defined', $worker->commandName));
                $worker->setExitCode(1);
                return;
            }

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');

            $input = new StringInput($worker->commandInput);
            $output = new NullOutput();

            $eventDispatcher->dispatch(new WorkerStartEvent($worker));
            $exitCode = $application->run($input, $output);
            $eventDispatcher->dispatch(new WorkerStopEvent($worker));
            $worker->setExitCode($exitCode);
        });
    }

    private function initializeSymfonySupervisedCommandWorker(SymfonySupervisedCommandWorker $worker): void
    {
        $appLoader = $this->appLoader;

        $worker->onStart(priority: -1, onStart: static function (SymfonySupervisedCommandWorker $worker) use ($appLoader): void {
            $_SERVER['APP_RUNTIME_MODE'] = 'worker=1';
            $kernel = $appLoader->loadApp();
            $kernel->boot();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            if (!$application->has($worker->commandName)) {
                $worker->logger->error(\sprintf('Command "%s" is not defined', $worker->commandName));
                $worker->stop(1);
                return;
            }

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');

            $input = new StringInput($worker->commandInput);
            $output = new NullOutput();

            $eventDispatcher->dispatch(new WorkerStartEvent($worker));
            $application->run($input, $output);
            $eventDispatcher->dispatch(new WorkerStopEvent($worker));
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
