<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessStartedEvent;
use Revolt\EventLoop;

/**
 * @internal
 */
final readonly class MetricsHandler
{
    private const UPDATE_INTERVAL_SECONDS = 5;

    public function __construct(RegistryInterface $registry, WorkerPool $pool, MessageHandlerInterface $handler)
    {
        $tasksGauge = $registry->registerGauge(
            namespace: Server::SHORTNAME,
            name: 'scheduler_tasks',
            help: 'Current number of registered tasks',
        );

        $runsCounter = $registry->registerCounter(
            namespace: Server::SHORTNAME,
            name: 'scheduler_task_runs_total',
            help: 'Total number of task executions',
        );

        $handler->subscribe(ProcessStartedEvent::class, static function (ProcessStartedEvent $message) use ($runsCounter): void {
            $runsCounter->inc();
        });

        $heartbeat = static function () use ($pool, $tasksGauge): void {
            $workers = $pool->getWorkerInfos();
            $tasksGauge->set(\count($workers));
        };

        EventLoop::unreference(EventLoop::delay(0.1, $heartbeat));
        EventLoop::unreference(EventLoop::repeat(self::UPDATE_INTERVAL_SECONDS, $heartbeat));
    }
}
