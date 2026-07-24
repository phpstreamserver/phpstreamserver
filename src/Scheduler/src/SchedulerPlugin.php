<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler;

use Amp\Future;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\WorkerInterface;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use PHPStreamServer\Plugin\Scheduler\Command\SchedulerCommand;
use PHPStreamServer\Plugin\Scheduler\Internal\MetricsHandler;
use PHPStreamServer\Plugin\Scheduler\Internal\Scheduler;
use PHPStreamServer\Plugin\Scheduler\Worker\ScheduledWorker;
use Revolt\EventLoop\Suspension;

/**
 * @extends Plugin<ScheduledWorker>
 */
final class SchedulerPlugin extends Plugin
{
    private Scheduler $scheduler;
    private MessageHandlerInterface $handler;

    public function __construct()
    {
    }

    protected function beforeStart(): void
    {
        /** @var int $stopTimeout */
        $stopTimeout = $this->masterContainer->getParameter('stop_timeout');
        $this->scheduler = new Scheduler($stopTimeout);
    }

    public function onStart(): void
    {
        $suspension = $this->masterContainer->getService(Suspension::class);
        $logger = &$this->masterContainer->getService(LoggerInterface::class);
        $bus = $this->masterContainer->getService(MessageBusInterface::class);
        $this->handler = $this->masterContainer->getService(MessageHandlerInterface::class);

        $this->scheduler->start($suspension, $logger, $bus, $this->handler);
    }

    public function afterStart(): void
    {
        if (\interface_exists(RegistryInterface::class) && $this->masterContainer->has(RegistryInterface::class)) {
            $registry = $this->masterContainer->getService(RegistryInterface::class);
            new MetricsHandler($registry, $this->scheduler->pool, $this->handler);
        }
    }

    public function registerWorker(WorkerInterface $worker): void
    {
        $this->scheduler->registerWorker($worker);
    }

    public function unregisterWorker(int $workerId): void
    {
        $this->scheduler->unregisterWorker($workerId);
    }

    public function onStop(): Future
    {
        return $this->scheduler->stop();
    }

    public function registerCommands(): iterable
    {
        return [
            new SchedulerCommand(),
        ];
    }
}
