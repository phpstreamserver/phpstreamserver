<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Symfony\Command\StartCommand;
use PHPStreamServer\Symfony\Event\ProcessReloadEvent;
use PHPStreamServer\Symfony\Event\ProcessStartEvent;
use PHPStreamServer\Symfony\Event\ProcessStopEvent;
use PHPStreamServer\Symfony\Worker\SymfonyCommandPeriodicProcess;
use PHPStreamServer\Symfony\Worker\SymfonyCommandWorkerProcess;
use PHPStreamServer\Symfony\Worker\SymfonyServerProcess;
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
    public function __construct(private AppLoader $appLoader)
    {
    }

    public function addWorker(Process $worker): void
    {
        if($worker instanceof SymfonyServerProcess) {
            $this->initializeSymfonyServerProcess($worker);
        } elseif ($worker instanceof SymfonyCommandPeriodicProcess) {
            $this->initializeSymfonyPeriodicProcess($worker);
        } elseif ($worker instanceof SymfonyCommandWorkerProcess) {
            $this->initializeSymfonyWorkerProcess($worker);
        }
    }

    private function initializeSymfonyServerProcess(SymfonyServerProcess $worker): void
    {
        $appLoader = $this->appLoader;

        $worker->onStart(priority: -1, onStart: static function (SymfonyServerProcess $worker) use ($appLoader): void {
            $_SERVER['APP_RUNTIME_MODE'] = 'web=1';
            $kernel = $appLoader->createKernel();
            $kernel->boot();

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessStartEvent($worker));
        });

        $worker->onStop(priority: 1000, onStop: static function (SymfonyServerProcess $worker): void {
            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessStopEvent($worker));
        });

        $worker->onReload(priority: 1000, onReload: static function (SymfonyServerProcess $worker): void {
            /** @var KernelInterface $kernel */
            $kernel = $worker->container->getService('kernel');

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessReloadEvent($worker));
        });
    }

    private function initializeSymfonyPeriodicProcess(SymfonyCommandPeriodicProcess $worker): void
    {
        $appLoader = $this->appLoader;

        $worker->onStart(priority: -1, onStart: static function (SymfonyCommandPeriodicProcess $worker) use ($appLoader): void {
            $kernel = $appLoader->createKernel();
            $kernel->boot();

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessStartEvent($worker));

            $application = new Application($kernel);
            $application->setAutoExit(false);

            if (!$application->has($worker->commandWithoutArguments)) {
                $worker->logger->error(\sprintf('Command "%s" is not defined', $worker->commandWithoutArguments));
                $worker->setExitCode(1);
                return;
            }

            $input = new StringInput($worker->command);
            $output = new NullOutput();
            $exitCode = $application->run($input, $output);
            $worker->setExitCode($exitCode);
        });
    }

    private function initializeSymfonyWorkerProcess(SymfonyCommandWorkerProcess $worker): void
    {
        $appLoader = $this->appLoader;

        $worker->onStart(priority: -1, onStart: static function (SymfonyCommandWorkerProcess $worker) use ($appLoader): void {
            $kernel = $appLoader->createKernel();
            $kernel->boot();

            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');
            $eventDispatcher->dispatch(new ProcessStartEvent($worker));

            $application = new Application($kernel);
            $application->setAutoExit(false);

            if (!$application->has($worker->commandWithoutArguments)) {
                $worker->logger->error(\sprintf('Command "%s" is not defined', $worker->commandWithoutArguments));
                $worker->stop(1);
                return;
            }

            $input = new StringInput($worker->command);
            $output = new NullOutput();
            $exitCode = $application->run($input, $output);
            $worker->stop($exitCode);
        });
    }

    public function onStart(): void
    {
        $cacheDir = $this->appLoader->getCacheDir();
        $phpSSCacheFile = $cacheDir . '/phpss_cache.php';

        $pidFile = $this->masterContainer->getParameter('pid_file');
        $socketFile = $this->masterContainer->getParameter('socket_file');
        $pid = $this->masterContainer->getParameter('pid');
        $cacheArray = [$pidFile, $socketFile, $pid];

        $cacheData = '<?php return ' . \var_export($cacheArray, true) . ';';

        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0775, true);
        }

        \file_put_contents($phpSSCacheFile, $cacheData);
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
