<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use Amp\DeferredFuture;
use Amp\Future;
use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Core\Internal\SIGCHLDHandler;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin\Scheduler\Message\GetWorkersCommand;
use PHPStreamServer\Plugin\Scheduler\Message\ProcessStartedEvent;
use PHPStreamServer\Plugin\Scheduler\Worker\ScheduledWorker;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

use function PHPStreamServer\Core\strSignal;

/**
 * @internal
 */
final class Scheduler
{
    private LoggerInterface $logger;
    public MessageBusInterface $messageBus;
    public MessageHandlerInterface $messageHandler;
    public readonly WorkerPool $pool;
    private Suspension $suspension;
    private DeferredFuture|null $stopFuture = null;
    private array $scheduledDelaysById = [];

    public function __construct(private readonly int $stopTimeout)
    {
        $this->pool = new WorkerPool();
    }

    public function start(Suspension $suspension, LoggerInterface &$logger, MessageBusInterface &$messageBus, MessageHandlerInterface &$messageHandler): void
    {
        $this->suspension = $suspension;
        $this->logger = &$logger;
        $this->messageBus = &$messageBus;
        $this->messageHandler = &$messageHandler;

        SIGCHLDHandler::onChildProcessExit($this->onChildStop(...));

        $pool = $this->pool;
        $this->messageHandler->subscribe(GetWorkersCommand::class, static function () use ($pool): array {
            return $pool->getWorkerInfos();
        });
    }

    public function registerWorker(ScheduledWorker $worker): void
    {
        try {
            $this->pool->addWorker($worker);
        } catch (\InvalidArgumentException) {
            $this->logger->warning(\sprintf('Scheduled worker "%s" was not registered; schedule "%s" is invalid', $worker->name, $worker->schedule));

            return;
        }

        $this->scheduleWorker($worker);
    }

    public function unregisterWorker(int $workerId): void
    {
        if (null === $worker = $this->pool->getWorkerInfoById($workerId)) {
            return;
        }

        $this->pool->removeWorker($worker->id);

        if (isset($this->scheduledDelaysById[$worker->id])) {
            EventLoop::cancel($this->scheduledDelaysById[$worker->id]);
            unset($this->scheduledDelaysById[$worker->id]);
        }
    }

    private function scheduleWorker(ScheduledWorker $worker): bool
    {
        if ($this->stopFuture !== null) {
            return false;
        }

        if (isset($this->scheduledDelaysById[$worker->id])) {
            EventLoop::cancel($this->scheduledDelaysById[$worker->id]);
        }

        $currentDate = new \DateTimeImmutable('now');
        $nextRunDate = $this->pool->calculateNextRunDate($worker->id, $currentDate);

        if ($nextRunDate === null) {
            $this->unregisterWorker($worker->id);

            return false;
        }

        $delay = (float) $nextRunDate->format('U.u') - (float) $currentDate->format('U.u');
        $delay = \max(0.0, $delay);
        $this->scheduledDelaysById[$worker->id] = EventLoop::delay($delay, function () use ($worker): void {
            unset($this->scheduledDelaysById[$worker->id]);
            $this->callWorker($worker);
        });

        return true;
    }

    private function callWorker(ScheduledWorker $worker): void
    {
        // Do not call if scheduler is stopping
        if ($this->stopFuture !== null) {
            return;
        }

        // Reschedule a task without running it if the previous task is still running
        if ($this->pool->isWorkerRunning($worker->id)) {
            if ($this->scheduleWorker($worker)) {
                $this->logger->info(\sprintf('Scheduled worker "%s" is already running; scheduling the next run', $worker->name));
            }

            return;
        }

        // Spawn process
        if (0 === $pid = $this->spawnWorker($worker)) {
            return;
        }

        $this->logger->info(\sprintf('Scheduled worker "%s" [PID:%d] started', $worker->name, $pid));
        $this->scheduleWorker($worker);

        $bus = $this->messageBus;
        EventLoop::queue(static function () use ($bus, $worker, $pid): void {
            $bus->dispatch(new ProcessStartedEvent($worker->id, $pid));
        });
    }

    private function spawnWorker(ScheduledWorker $worker): int
    {
        $pid = \pcntl_fork();
        if ($pid > 0) {
            // Master process
            $this->pool->addProcess($worker->id, $pid);
            return $pid;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker);
            return 0;
        } else {
            throw new PHPStreamServerException('Fork failed');
        }
    }

    private function onChildStop(int $pid, int $exitCode, int|null $terminationSignal): void
    {
        if (null === $workerInfo = $this->pool->getWorkerInfoByPid($pid)) {
            return;
        }

        $this->pool->removeProcess($pid);

        if ($terminationSignal !== null) {
            $this->logger->warning(\sprintf('Scheduled worker "%s" [PID:%d] terminated with signal %s (%d)', $workerInfo->name, $pid, strSignal($terminationSignal), $terminationSignal));
        } elseif ($exitCode === 0) {
            $this->logger->info(\sprintf('Scheduled worker "%s" [PID:%d] exited with code %d', $workerInfo->name, $pid, $exitCode));
        } else {
            $this->logger->warning(\sprintf('Scheduled worker "%s" [PID:%d] exited with code %d', $workerInfo->name, $pid, $exitCode));
        }

        if ($this->stopFuture !== null && !$this->stopFuture->isComplete() && !$this->pool->hasRunningWorkers()) {
            $this->stopFuture->complete();
        }
    }

    public function stop(): Future
    {
        $this->stopFuture = new DeferredFuture();

        foreach ($this->scheduledDelaysById as $callbackId) {
            EventLoop::cancel($callbackId);
        }
        $this->scheduledDelaysById = [];

        if (!$this->pool->hasRunningWorkers()) {
            $this->stopFuture->complete();
        } else {
            $stopTimeout = $this->stopTimeout;
            $pool = $this->pool;
            $logger = $this->logger;
            $stopFuture = $this->stopFuture;
            $stopCallbackId = EventLoop::delay($stopTimeout, static function () use ($stopTimeout, $pool, $logger, $stopFuture): void {
                // Send the SIGKILL signal to all running scheduled worker processes after the timeout
                foreach ($pool->getWorkerInfos() as $worker) {
                    if (null === $pid = $pool->getPidById($worker->id)) {
                        continue;
                    }
                    \posix_kill($pid, SIGKILL);
                    $logger->notice(\sprintf('Scheduled worker "%s" [PID:%d] was killed after a %d-second timeout', $worker->name, $pid, $stopTimeout));
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
