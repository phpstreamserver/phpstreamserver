<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Worker;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\Exception\ProcessIdentityException;
use PHPStreamServer\Core\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Runtime\ErrorHandler;
use PHPStreamServer\Core\Runtime\ProcessIdentity;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Core\WorkerInterface;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

use function PHPStreamServer\Core\generateWorkerId;

class ScheduledWorker implements WorkerInterface
{
    private int $exitCode = 0;
    private readonly int $id;
    private readonly int $pid;
    private readonly string $name;

    public readonly ContainerInterface $container;
    public readonly LoggerInterface $logger;
    public readonly MessageBusInterface $bus;

    /**
     * @var array<string, \Closure(static): void>
     */
    private array $onStartCallbacks = [];

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
        public readonly int $jitter = 0,
        private string|null $user = null,
        private string|null $group = null,
        \Closure|null $onStart = null,
    ) {
        if ($name !== null && $name !== '') {
            $this->name = \trim($name);
        }

        if ($onStart !== null) {
            $this->onStart($onStart);
        }
    }

    /**
     * @internal
     */
    final public function run(ContainerInterface $workerContainer): int
    {
        // Some command-line SAPIs (e.g., phpdbg) don't have this function.
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: %s', Server::NAME, $this->getName()));
        }

        EventLoop::setDriver((new DriverFactory())->create());

        $this->pid = \posix_getpid();
        $this->container = $workerContainer;
        $this->logger = $workerContainer->getService(LoggerInterface::class);
        $this->bus = $workerContainer->getService(MessageBusInterface::class);

        $exitCode = &$this->exitCode;
        ErrorHandler::register($this->logger);
        EventLoop::setErrorHandler(static function (\Throwable $exception) use (&$exitCode): void {
            ErrorHandler::handleException($exception);
            $exitCode = 1;
        });

        try {
            ProcessIdentity::switchTo($this->user, $this->group);
        } catch (ProcessIdentityException $e) {
            $this->logger->error(\sprintf('Worker "%s" failed to change process identity: %s', $this->getName(), $e->getMessage()));
            $this->onStartCallbacks = [];
        }

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
        return $this->id ??= generateWorkerId();
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getName(): string
    {
        return $this->name ??= 'scheduled worker ' . $this->getId();
    }

    final public function getUser(): string
    {
        return $this->user ?? ProcessIdentity::getEffectiveUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? ProcessIdentity::getEffectiveGroup();
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

    public function isSerializable(): bool
    {
        return true;
    }
}
