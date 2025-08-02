<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Status;

use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessScheduledEvent;
use PHPStreamServer\Plugin\Scheduler\Worker\PeriodicProcess;

final class SchedulerStatus
{
    /**
     * @var array<int, PeriodicWorkerInfo>
     */
    private array $periodicWorkers = [];

    public function __construct()
    {
    }

    public function subscribeToWorkerMessages(MessageHandlerInterface $handler): void
    {
        $periodicWorkers = &$this->periodicWorkers;
        $handler->subscribe(ProcessScheduledEvent::class, static function (ProcessScheduledEvent $message) use (&$periodicWorkers): void {
            $periodicWorkers[$message->id]->nextRunDate = $message->nextRunDate;
        });
    }

    public function addWorker(PeriodicProcess $worker): void
    {
        $this->periodicWorkers[$worker->id] = new PeriodicWorkerInfo(
            user: $worker->getUser(),
            name: $worker->name,
            schedule: $worker->schedule,
        );
    }

    public function getPeriodicTasksCount(): int
    {
        return \count($this->periodicWorkers);
    }

    /**
     * @return list<PeriodicWorkerInfo>
     */
    public function getPeriodicWorkers(): array
    {
        return \array_values($this->periodicWorkers);
    }
}
