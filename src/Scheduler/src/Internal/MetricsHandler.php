<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessStartedEvent;
use PHPStreamServer\Plugin\Scheduler\Status\SchedulerStatus;

/**
 * @internal
 */
final readonly class MetricsHandler
{
    public function __construct(
        RegistryInterface $registry,
        SchedulerStatus $schedulerStatus,
        MessageHandlerInterface $handler,
    ) {
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

        $tasksGauge->set($schedulerStatus->getPeriodicTasksCount());
    }
}
