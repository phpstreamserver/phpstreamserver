<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use Amp\DeferredFuture;
use Amp\Future;
use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Core\Internal\SIGCHLDHandler;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessScheduledEvent;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessStartedEvent;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerFactory;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerInterface;
use PHPStreamServer\Plugin\Scheduler\Worker\PeriodicProcess;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

use function Amp\weakClosure;

/**
 * @internal
 */
final class Scheduler
{
    private MessageBusInterface $messageBus;
    private WorkerPool $pool;
    private LoggerInterface $logger;
    private Suspension $suspension;
    private DeferredFuture|null $stopFuture = null;

    public function __construct()
    {
        $this->pool = new WorkerPool();
    }

    public function addWorker(PeriodicProcess $worker): void
    {
        $this->pool->addWorker($worker);
    }

    public function start(Suspension $suspension, LoggerInterface $logger, MessageBusInterface $messageBus): void
    {
        $this->suspension = $suspension;
        $this->logger = $logger;
        $this->messageBus = $messageBus;

        SIGCHLDHandler::onChildProcessExit(weakClosure($this->onChildStop(...)));

        foreach ($this->pool->getWorkers() as $worker) {
            /** @var PeriodicProcess $worker */
            try {
                $trigger = TriggerFactory::create($worker->schedule, $worker->jitter);
            } catch (\InvalidArgumentException) {
                $this->logger->warning(\sprintf('Periodic process "%s" skipped. Schedule "%s" is not in valid format', $worker->name, $worker->schedule));
                continue;
            }

            $this->scheduleWorker($worker, $trigger);
        }
    }

    private function scheduleWorker(PeriodicProcess $worker, TriggerInterface $trigger): bool
    {
        if ($this->stopFuture !== null) {
            return false;
        }

        $currentDate = new \DateTimeImmutable();
        $nextRunDate = $trigger->getNextRunDate($currentDate);

        if ($nextRunDate !== null) {
            $delay = $nextRunDate->getTimestamp() - $currentDate->getTimestamp();
            EventLoop::delay($delay, function () use ($worker, $trigger): void {
                $this->startWorker($worker, $trigger);
            });
        }

        EventLoop::defer(function () use ($worker, $nextRunDate): void {
            $this->messageBus->dispatch(new ProcessScheduledEvent($worker->id, $nextRunDate));
        });

        return true;
    }

    private function startWorker(PeriodicProcess $worker, TriggerInterface $trigger): void
    {
        // Reschedule a task without running it if the previous task is still running
        if ($this->pool->isWorkerRun($worker)) {
            if ($this->scheduleWorker($worker, $trigger)) {
                $this->logger->info(\sprintf('Periodic process "%s" is already running. Rescheduled', $worker->name));
            }
            return;
        }

        // Spawn process
        if (0 === $pid = $this->spawnWorker($worker)) {
            return;
        }

        $this->logger->info(\sprintf('Periodic process "%s" [pid:%s] started', $worker->name, $pid));
        $this->scheduleWorker($worker, $trigger);
        $this->messageBus->dispatch(new ProcessStartedEvent($worker->id));
    }

    private function spawnWorker(PeriodicProcess $worker): int
    {
        $pid = \pcntl_fork();
        if ($pid > 0) {
            // Master process
            $this->pool->addChild($worker, $pid);
            return $pid;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker);
            return 0;
        } else {
            throw new PHPStreamServerException('fork fail');
        }
    }

    private function onChildStop(int $pid, int $exitCode): void
    {
        if (null === $worker = $this->pool->getWorkerByPid($pid)) {
            return;
        }

        $this->pool->deleteChild($worker);
        $this->logger->info(\sprintf('Periodic process "%s" [pid:%s] exit with code %s', $worker->name, $pid, $exitCode));

        if ($this->stopFuture !== null && !$this->stopFuture->isComplete() && $this->pool->getProcessesCount() === 0) {
            $this->stopFuture->complete();
        }
    }

    public function stop(): Future
    {
        $this->stopFuture = new DeferredFuture();

        if ($this->pool->getProcessesCount() === 0) {
            $this->stopFuture->complete();
        }

        return $this->stopFuture->getFuture();
    }
}
