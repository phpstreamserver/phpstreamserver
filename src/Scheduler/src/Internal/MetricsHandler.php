<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\Metrics\Counter;
use PHPStreamServer\Plugin\Metrics\Gauge;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessStartedEvent;
use PHPStreamServer\Plugin\Scheduler\Status\SchedulerStatus;
use function Amp\weakClosure;

final readonly class MetricsHandler
{
    private Gauge $workersTotal;
    private Counter $runsTotal;

    public function __construct(
        RegistryInterface $registry,
        SchedulerStatus $schedulerStatus,
        MessageHandlerInterface $handler,
    ) {
        $this->workersTotal = $registry->registerGauge(
            namespace: Server::SHORTNAME,
            name: 'scheduler_tasks_total',
            help: 'Total number of tasks',
        );

        $this->runsTotal = $registry->registerCounter(
            namespace: Server::SHORTNAME,
            name: 'scheduler_task_runs_total',
            help: 'Total number of tasks call',
        );

        $handler->subscribe(ProcessStartedEvent::class, weakClosure(function (ProcessStartedEvent $message): void {
            $this->runsTotal->inc();
        }));

        $this->workersTotal->set($schedulerStatus->getPeriodicTasksCount());
    }
}
