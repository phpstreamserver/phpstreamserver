<?php

declare(strict_types=1);

namespace PHPStreamServer\Core;

use Amp\DeferredFuture;
use PHPStreamServer\Core\Exception\UserChangeException;
use PHPStreamServer\Core\Internal\ErrorHandler;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\Message\CompositeMessage;
use PHPStreamServer\Core\Message\ProcessHeartbeatEvent;
use PHPStreamServer\Core\Message\ProcessSpawnedEvent;
use PHPStreamServer\Core\MessageBus\GracefulMessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Plugin\Supervisor\Internal\ReloadStrategyStack;
use PHPStreamServer\Core\Plugin\Supervisor\SupervisorPlugin;
use PHPStreamServer\Core\ReloadStrategy\ReloadStrategy;
use PHPStreamServer\Core\Worker\ContainerInterface;
use PHPStreamServer\Core\Worker\ProcessUserChange;
use PHPStreamServer\Core\Worker\Status;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

class WorkerProcess implements Process
{
    use ProcessUserChange;

    final public const HEARTBEAT_PERIOD = 2;
    final public const RELOAD_EXIT_CODE = 100;
    private const GC_PERIOD = 180;

    private Status $status = Status::SHUTDOWN;
    private int $exitCode = 0;
    public readonly int $id;
    public readonly int $pid;
    public readonly string $name;
    public readonly ContainerInterface $container;
    public readonly LoggerInterface $logger;
    public readonly GracefulMessageBusInterface $bus;
    private DeferredFuture|null $startingFuture;
    private readonly ReloadStrategyStack $reloadStrategyStack;

    /** @var array<\Closure(self): void> */
    private array $onStartCallbacks = [];

    /** @var array<\Closure(self): void> */
    private array $onStopCallbacks = [];

    /** @var array<\Closure(self): void> */
    private array $onReloadCallbacks = [];

    /**
     * @template T of self
     * @param null|\Closure(T):void $onStart
     * @param null|\Closure(T):void $onStop
     * @param null|\Closure(T):void $onReload
     * @param array<ReloadStrategy> $reloadStrategies
     */
    public function __construct(
        string $name = '',
        public readonly int $count = 1,
        public readonly bool $reloadable = true,
        private string|null $user = null,
        private string|null $group = null,
        \Closure|null $onStart = null,
        \Closure|null $onStop = null,
        \Closure|null $onReload = null,
        private array $reloadStrategies = [],
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;

        if ($name === '') {
            $this->name = 'worker_' . $this->id;
        } else {
            $this->name = $name;
        }

        if ($onStart !== null) {
            $this->onStart($onStart);
        }
        if ($onStop !== null) {
            $this->onStop($onStop);
        }
        if ($onReload !== null) {
            $this->onReload($onReload);
        }
    }

    /**
     * @internal
     */
    final public function run(ContainerInterface $workerContainer): int
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: worker process  %s', Server::NAME, $this->name));
        }

        EventLoop::setDriver((new DriverFactory())->create());

        $this->status = Status::STARTING;
        $this->pid = \posix_getpid();
        $this->container = $workerContainer;
        $this->logger = $workerContainer->getService(LoggerInterface::class);
        $this->bus = $workerContainer->getService(MessageBusInterface::class);

        ErrorHandler::register($this->logger);
        EventLoop::setErrorHandler(function (\Throwable $exception) {
            ErrorHandler::handleException($exception);
            $this->reloadStrategyStack->emitEvent($exception);
        });

        try {
            $this->setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), [(new \ReflectionObject($this))->getShortName() => $this->name]);
        }

        EventLoop::onSignal(SIGINT, static fn() => null);
        EventLoop::onSignal(SIGTERM, fn() => $this->stop());
        EventLoop::onSignal(SIGUSR1, fn() => $this->reload());

        // Force run garbage collection periodically
        EventLoop::repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });

        $this->reloadStrategyStack = new ReloadStrategyStack($this->reload(...), $this->reloadStrategies);
        $this->container->setService('reload_strategy_emitter', $this->reloadStrategyStack->emitEvent(...));
        unset($this->reloadStrategies);

        $heartbeatEvent = function (): ProcessHeartbeatEvent {
            return new ProcessHeartbeatEvent(
                pid: $this->pid,
                memory: \memory_get_usage(),
                time: \hrtime(true),
            );
        };

        $this->startingFuture = new DeferredFuture();

        EventLoop::repeat(self::HEARTBEAT_PERIOD, function () use ($heartbeatEvent) {
            $this->bus->dispatch($heartbeatEvent());
        });

        EventLoop::queue(function () use ($heartbeatEvent): void {
            $this->bus->dispatch(new CompositeMessage([
                new ProcessSpawnedEvent(
                    workerId: $this->id,
                    pid: $this->pid,
                    user: $this->getUser(),
                    name: $this->name,
                    reloadable: $this->reloadable,
                    startedAt: new \DateTimeImmutable('now'),
                ),
                $heartbeatEvent(),
            ]))->await();

            EventLoop::queue(function () {
                foreach ($this->onStartCallbacks as $onStartCallback) {
                    $onStartCallback($this);
                }
            });

            $this->status = Status::RUNNING;
            $this->startingFuture?->complete();
            $this->startingFuture = null;
        });

        EventLoop::run();

        return $this->exitCode;
    }

    /**
     * @return list<class-string<Plugin>>
     */
    public static function handleBy(): array
    {
        return [SupervisorPlugin::class];
    }

    final public function getUser(): string
    {
        return $this->user ?? getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? getCurrentGroup();
    }

    public function stop(int $code = 0): void
    {
        if ($this->status !== Status::STARTING && $this->status !== Status::RUNNING) {
            return;
        }

        $this->status = Status::STOPPING;
        $this->exitCode = $code;

        EventLoop::defer(function (): void {
            $this->startingFuture?->getFuture()->await();
            foreach ($this->onStopCallbacks as $onStopCallback) {
                $onStopCallback($this);
            }
            $this->bus->stop()->await();
            EventLoop::getDriver()->stop();
        });
    }

    public function reload(): void
    {
        if (!$this->reloadable) {
            return;
        }

        if ($this->status !== Status::STARTING && $this->status !== Status::RUNNING) {
            return;
        }

        $this->status = Status::STOPPING;
        $this->exitCode = self::RELOAD_EXIT_CODE;

        EventLoop::defer(function (): void {
            $this->startingFuture?->getFuture()->await();
            foreach ($this->onReloadCallbacks as $onReloadCallback) {
                $onReloadCallback($this);
            }
            $this->bus->stop()->await();
            EventLoop::getDriver()->stop();
        });
    }

    public function addReloadStrategy(ReloadStrategy ...$reloadStrategies): void
    {
        $this->reloadStrategyStack->addReloadStrategy(...$reloadStrategies);
    }

    /**
     * @param \Closure(self): void $onStart
     */
    public function onStart(\Closure $onStart, int $priority = 0): void
    {
        $this->onStartCallbacks[$priority . \uniqid()] = $onStart;
        \ksort($this->onStartCallbacks);
    }

    /**
     * @param \Closure(self): void $onStop
     */
    public function onStop(\Closure $onStop, int $priority = 0): void
    {
        $this->onStopCallbacks[$priority . \uniqid()] = $onStop;
        \ksort($this->onStopCallbacks);
    }

    /**
     * @param \Closure(self): void $onReload
     */
    public function onReload(\Closure $onReload, int $priority = 0): void
    {
        $this->onReloadCallbacks[$priority . \uniqid()] = $onReload;
        \ksort($this->onReloadCallbacks);
    }
}
