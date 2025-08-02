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
        $workersTotal = $registry->registerGauge(
            namespace: Server::SHORTNAME,
            name: 'scheduler_tasks_total',
            help: 'Total number of tasks',
        );

        $runsTotal = $registry->registerCounter(
            namespace: Server::SHORTNAME,
            name: 'scheduler_task_runs_total',
            help: 'Total number of tasks call',
        );

        $handler->subscribe(ProcessStartedEvent::class, static function (ProcessStartedEvent $message) use ($runsTotal): void {
            $runsTotal->inc();
        });

        $workersTotal->set($schedulerStatus->getPeriodicTasksCount());
    }
}
