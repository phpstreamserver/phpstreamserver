<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Worker;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\Exception\ConfigurationException;
use PHPStreamServer\Core\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\WorkerInterface;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerFactory;
use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerInterface;
use Revolt\EventLoop;

use function PHPStreamServer\Core\generateWorkerId;
use function PHPStreamServer\Core\getEffectiveGroup;
use function PHPStreamServer\Core\getEffectiveUser;

class ScheduledWorker implements WorkerInterface
{
    private int $exitCode = 0;
    protected readonly int $id;
    protected readonly int $pid;
    private readonly string $name;

    public readonly ContainerInterface $container;
    public readonly LoggerInterface $logger;
    public readonly MessageBusInterface $bus;

    /**
     * @var array<string, \Closure(static): void>
     */
    private array $onStartCallbacks = [];

    public readonly TriggerInterface $trigger;

    /**
     * $schedule can be one of the following formats:
     *
     *  * An integer or numeric string representing the frequency in seconds;
     *  * An ISO 8601 date-time format;
     *  * An ISO 8601 duration format;
     *  * A relative date format as supported by \DateInterval;
     *  * A cron expression;
     *
     * @param string $schedule Schedule in one of the formats described above
     * @param int $jitter Jitter in seconds that adds a random offset to the schedule
     * @param null|\Closure(self):void $onStart
     */
    public function __construct(
        string|null $name = null,
        public readonly string $schedule = '1 minute',
        int $jitter = 0,
        private readonly string|null $user = null,
        private readonly string|null $group = null,
        \Closure|null $onStart = null,
    ) {
        if ($name !== null && $name !== '') {
            $this->name = \trim($name);
        }

        if ($onStart !== null) {
            $this->onStart($onStart);
        }

        try {
            $this->trigger = TriggerFactory::create($schedule, $jitter);
        } catch (\InvalidArgumentException $e) {
            throw new ConfigurationException('schedule', $e->getMessage());
        }

        $this->id = generateWorkerId();
        /** @psalm-suppress DocblockTypeContradiction */
        $this->name ??= 'scheduled worker ' . $this->id;
    }

    /**
     * @internal
     */
    final public function run(ContainerInterface $workerContainer): int
    {
        $this->pid = \posix_getpid();
        $this->container = $workerContainer;
        $this->logger = $workerContainer->getService(LoggerInterface::class);
        $this->bus = $workerContainer->getService(MessageBusInterface::class);

        $exitCode = &$this->exitCode;
        $defaultHandler = EventLoop::getErrorHandler();
        EventLoop::setErrorHandler(static function (\Throwable $exception) use ($defaultHandler, &$exitCode): void {
            $defaultHandler?->__invoke($exception);
            $exitCode = 1;
        });

        EventLoop::unreference(EventLoop::onSignal(SIGINT, static fn() => null));

        EventLoop::queue(function (): void {
            foreach ($this->onStartCallbacks as $onStartCallback) {
                $onStartCallback($this);
            }
        });

        EventLoop::run();

        return $this->exitCode;
    }

    public static function handledBy(): array
    {
        return [SchedulerPlugin::class];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    final public function getUser(): string
    {
        return $this->user ?? getEffectiveUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? getEffectiveGroup();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getMessageBus(): MessageBusInterface
    {
        return $this->bus;
    }

    /**
     * @param \Closure(static): void $onStart
     */
    public function onStart(\Closure $onStart, int $priority = 0): void
    {
        $this->onStartCallbacks[$priority . ':' . \uniqid()] = $onStart;
        \ksort($this->onStartCallbacks, SORT_NUMERIC);
    }

    public function setExitCode(int $exitCode): void
    {
        $this->exitCode = $exitCode;
    }
}
