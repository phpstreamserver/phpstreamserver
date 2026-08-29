<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Internal;

use Amp\DeferredFuture;
use Amp\Future;
use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Core\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Runtime\ChildProcessRegistry;
use PHPStreamServer\Core\Runtime\SIGCHLDHandler;
use PHPStreamServer\Plugin\Scheduler\Command\GetWorkersCommand;
use PHPStreamServer\Plugin\Scheduler\Event\ProcessStartedEvent;
use PHPStreamServer\Plugin\Scheduler\Worker\ScheduledWorker;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

use function PHPStreamServer\Core\strSignal;

/**
 * @internal
 */
final class Scheduler
{
    private const WALL_CLOCK_RECHECK_INTERVAL = 300.0;

    private LoggerInterface $logger;
    public MessageBusInterface $messageBus;
    public MessageHandlerInterface $messageHandler;
    public ChildProcessRegistry $childProcessRegistry;
    public readonly WorkerPool $pool;
    private Suspension $suspension;
    private DeferredFuture|null $stopFuture = null;
    private array $scheduledDelaysById = [];

    public function __construct(private readonly int $stopTimeout)
    {
        $this->pool = new WorkerPool();
    }

    public function start(ContainerInterface $container): void
    {
        $this->suspension = $container->getService(Suspension::class);
        $this->logger = &$container->getService(LoggerInterface::class);
        $this->messageBus = &$container->getService(MessageBusInterface::class);
        $this->messageHandler = &$container->getService(MessageHandlerInterface::class);
        $this->childProcessRegistry = $container->getService(ChildProcessRegistry::class);

        SIGCHLDHandler::onChildProcessExit($this->onChildStop(...));

        $pool = $this->pool;
        $this->messageHandler->subscribe(GetWorkersCommand::class, static function () use ($pool): array {
            return $pool->getWorkerInfos();
        });
    }

    public function registerWorker(ScheduledWorker $worker, string|null $factoryId): void
    {
        $currentDate = new \DateTimeImmutable('now');
        $nextRunDate = $worker->trigger->getNextRunDate($currentDate);

        if ($nextRunDate === null) {
            return;
        }

        $this->pool->addWorker($worker, $factoryId, $currentDate, $nextRunDate);
        $this->scheduleWorker(worker: $worker, currentDate: $currentDate, runAt: $nextRunDate);
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

    private function scheduleWorker(ScheduledWorker $worker, \DateTimeImmutable|null $currentDate = null, \DateTimeImmutable|null $runAt = null): bool
    {
        if ($this->stopFuture !== null) {
            return false;
        }

        $id = $worker->getId();

        if (isset($this->scheduledDelaysById[$id])) {
            EventLoop::cancel($this->scheduledDelaysById[$id]);
        }

        $currentDate ??= new \DateTimeImmutable('now');

        if ($runAt === null) {
            $runAt = $worker->trigger->getNextRunDate($currentDate);

            if ($runAt === null) {
                $this->unregisterWorker($id);

                return false;
            }

            $this->pool->updateNextRunDate($id, $runAt);
        }

        $microsecondsDelay = ($runAt->getTimestamp() - $currentDate->getTimestamp()) * 1_000_000 + (int) $runAt->format('u') - (int) $currentDate->format('u');
        $delay = \min($microsecondsDelay * 1e-6, self::WALL_CLOCK_RECHECK_INTERVAL);

        if ($delay <= 0.0) {
            $this->callWorker($worker);

            return true;
        }

        $this->scheduledDelaysById[$id] = EventLoop::delay($delay, function () use ($id, $worker, $runAt): void {
            unset($this->scheduledDelaysById[$id]);
            $this->scheduleWorker(worker: $worker, runAt: $runAt);
        });

        return true;
    }

    private function callWorker(ScheduledWorker $worker): void
    {
        // Do not call if scheduler is stopping
        if ($this->stopFuture !== null) {
            return;
        }

        $id = $worker->getId();

        // Reschedule a task without running it if the previous task is still running
        if ($this->pool->isWorkerRunning($id)) {
            if ($this->scheduleWorker(worker: $worker)) {
                $this->logger->info(\sprintf('Scheduled worker "%s" is already running; scheduling the next run', $worker->getName()));
            }

            return;
        }

        // Spawn process
        if (0 === $pid = $this->spawnWorker($worker)) {
            return;
        }

        $this->logger->info(\sprintf('Scheduled worker "%s" [PID:%d] started', $worker->getName(), $pid));
        $this->scheduleWorker(worker: $worker);

        $bus = $this->messageBus;
        EventLoop::queue(static function () use ($bus, $id, $pid): void {
            $bus->dispatch(new ProcessStartedEvent($id, $pid));
        });
    }

    private function spawnWorker(ScheduledWorker $worker): int
    {
        $forkError = '';
        \set_error_handler(error_levels: \E_WARNING, callback: static function (int $code, string $message) use (&$forkError): true {
            $forkError = \trim(\str_replace('pcntl_fork():', '', $message));
            return true;
        });

        try {
            $pid = \pcntl_fork();
        } finally {
            \restore_error_handler();
        }

        if ($pid > 0) {
            // Master process
            $this->pool->addProcess($worker->getId(), $pid);
            $this->childProcessRegistry->register($pid);
            return $pid;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker);
            return 0;
        } else {
            throw new PHPStreamServerException(\sprintf('Fork failed: %s', $forkError));
        }
    }

    private function onChildStop(int $pid, int $exitCode, int|null $terminationSignal): void
    {
        if (null === $workerInfo = $this->pool->getWorkerInfoByPid($pid)) {
            return;
        }

        $this->pool->removeProcess($pid);
        $this->childProcessRegistry->unregister($pid);

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
