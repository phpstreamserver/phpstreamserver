<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use Amp\DeferredFuture;
use Amp\Future;
use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Core\Internal\SIGCHLDHandler;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin\Scheduler\Message\GetSchedulerStatusCommand;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessScheduledEvent;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessStartedEvent;
use PHPStreamServer\Plugin\Scheduler\Status\SchedulerStatus;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerFactory;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerInterface;
use PHPStreamServer\Plugin\Scheduler\Worker\PeriodicProcess;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

use function Amp\weakClosure;
use function PHPStreamServer\Core\generateWorkerId;

/**
 * @internal
 */
final class Scheduler
{
    private bool $running = false;
    private LoggerInterface $logger;
    public MessageBusInterface $messageBus;
    public MessageHandlerInterface $messageHandler;
    private WorkerPool $pool;
    public readonly SchedulerStatus $schedulerStatus;
    private \WeakMap $triggerMap;
    private Suspension $suspension;
    private DeferredFuture|null $stopFuture = null;

    public function __construct(private readonly int $stopTimeout)
    {
        $this->pool = new WorkerPool();
        $this->schedulerStatus = new SchedulerStatus();
        $this->triggerMap = new \WeakMap();
    }

    public function registerWorker(PeriodicProcess $worker): void
    {
        $workerId = generateWorkerId();
        $worker->assignId($workerId);

        $this->pool->registerWorker($worker);
        $this->schedulerStatus->addWorker($worker);

        if ($this->running) {
            $this->scheduleWorker($worker);
            $this->logger->info(\sprintf('Worker "%s" was registered with the scheduler', $worker->name));
        }
    }

    public function start(Suspension $suspension, LoggerInterface &$logger, MessageBusInterface &$messageBus, MessageHandlerInterface &$messageHandler): void
    {
        $this->running = true;
        $this->suspension = $suspension;
        $this->logger = &$logger;
        $this->messageBus = &$messageBus;
        $this->messageHandler = &$messageHandler;

        SIGCHLDHandler::onChildProcessExit(weakClosure($this->onChildStop(...)));

        $this->schedulerStatus->subscribeToWorkerMessages($this->messageHandler);

        $schedulerStatus = $this->schedulerStatus;

        $this->messageHandler->subscribe(GetSchedulerStatusCommand::class, static function () use ($schedulerStatus): SchedulerStatus {
            return $schedulerStatus;
        });

        foreach ($this->pool->getWorkers() as $worker) {
            $this->scheduleWorker($worker);
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function getTriggerForWorker(PeriodicProcess $worker): TriggerInterface
    {
        if (!$this->triggerMap->offsetExists($worker)) {
            $trigger = TriggerFactory::create($worker->schedule, $worker->jitter);
            $this->triggerMap->offsetSet($worker, $trigger);
        }

        return $this->triggerMap->offsetGet($worker);
    }

    private function scheduleWorker(PeriodicProcess $worker): bool
    {
        if ($this->stopFuture !== null) {
            return false;
        }

        try {
            $trigger = $this->getTriggerForWorker($worker);
        } catch (\InvalidArgumentException) {
            $this->logger->warning(\sprintf('Periodic process "%s" was skipped; schedule "%s" is invalid', $worker->name, $worker->schedule));

            return false;
        }

        $currentDate = new \DateTimeImmutable();
        $nextRunDate = $trigger->getNextRunDate($currentDate);

        if ($nextRunDate !== null) {
            $delay = $nextRunDate->getTimestamp() - $currentDate->getTimestamp();
            EventLoop::delay($delay, weakClosure(function () use ($worker): void {
                $this->callWorker($worker);
            }));
        }

        $bus = $this->messageBus;
        EventLoop::defer(static function () use ($bus, $worker, $nextRunDate): void {
            $bus->dispatch(new ProcessScheduledEvent($worker->id, $nextRunDate));
        });

        return true;
    }

    private function callWorker(PeriodicProcess $worker): void
    {
        // Reschedule a task without running it if the previous task is still running
        if ($this->pool->isWorkerRunning($worker)) {
            if ($this->scheduleWorker($worker)) {
                $this->logger->info(\sprintf('Periodic process "%s" is already running; scheduling the next run', $worker->name));
            }

            return;
        }

        // Spawn process
        if (0 === $pid = $this->spawnWorker($worker)) {
            return;
        }

        $this->logger->info(\sprintf('Periodic process "%s" [PID: %d] started', $worker->name, $pid));
        $this->scheduleWorker($worker);

        $bus = $this->messageBus;
        EventLoop::queue(static function () use ($bus, $worker): void {
            $bus->dispatch(new ProcessStartedEvent($worker->id));
        });
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
            throw new PHPStreamServerException('Fork failed');
        }
    }

    private function onChildStop(int $pid, int $exitCode): void
    {
        if (null === $worker = $this->pool->getWorkerByPid($pid)) {
            return;
        }

        $this->pool->removeChild($worker);
        $this->logger->info(\sprintf('Periodic process "%s" [PID: %d] exited with code %d', $worker->name, $pid, $exitCode));

        if ($this->stopFuture !== null && !$this->stopFuture->isComplete() && $this->pool->getProcessCount() === 0) {
            $this->stopFuture->complete();
        }
    }

    public function stop(): Future
    {
        $this->stopFuture = new DeferredFuture();

        if ($this->pool->getProcessCount() === 0) {
            $this->stopFuture->complete();
        } else {
            $stopTimeout = $this->stopTimeout;
            $pool = $this->pool;
            $logger = $this->logger;
            $stopFuture = $this->stopFuture;
            $stopCallbackId = EventLoop::delay($stopTimeout, static function () use ($stopTimeout, $pool, $logger, $stopFuture): void {
                // Send the SIGKILL signal to all running periodic processes after the timeout
                foreach ($pool->getWorkers() as $worker) {
                    if (null === $pid = $pool->getPidByWorker($worker)) {
                        continue;
                    }
                    \posix_kill($pid, SIGKILL);
                    $logger->notice(\sprintf('Periodic process "%s" [PID: %d] was killed after a %d-second timeout', $worker->name, $pid, $stopTimeout));
                }
                $stopFuture->complete();
            });

            $this->stopFuture->getFuture()->finally(static function () use ($stopCallbackId) {
                EventLoop::cancel($stopCallbackId);
            });
        }

        return $this->stopFuture->getFuture();
    }
}
