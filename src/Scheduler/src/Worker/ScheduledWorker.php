<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Worker;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\Exception\PHPStreamServerException;
use PHPStreamServer\Core\Exception\UserChangeException;
use PHPStreamServer\Core\Internal\ErrorHandler;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Core\WorkerInterface;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

use function PHPStreamServer\Core\getCurrentGroup;
use function PHPStreamServer\Core\getCurrentUser;
use function PHPStreamServer\Core\setUserAndGroup;

class ScheduledWorker implements WorkerInterface
{
    private int $exitCode = 0;
    public readonly int $id;
    public readonly int $pid;
    public readonly string $name;
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
        string $name = '',
        public readonly string $schedule = '1 minute',
        public readonly int $jitter = 0,
        private string|null $user = null,
        private string|null $group = null,
        \Closure|null $onStart = null,
    ) {
        $name = \trim($name);
        if ($name !== '') {
            $this->name = $name;
        }

        if ($onStart !== null) {
            $this->onStart($onStart);
        }
    }

    /**
     * @internal
     * @psalm-suppress RedundantPropertyInitializationCheck
     */
    final public function assignId(int $id): void
    {
        if (isset($this->id)) {
            throw new PHPStreamServerException('Worker ID has already been assigned');
        }

        $this->id = $id;
        $this->name ??= 'scheduled worker ' . $id;
    }

    /**
     * @internal
     */
    final public function run(ContainerInterface $workerContainer): int
    {
        // Some command-line SAPIs (e.g., phpdbg) don't have this function.
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: %s', Server::NAME, $this->name));
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
            setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->error(\sprintf('Worker "%s" failed to change process identity: %s', $this->name, $e->getMessage()));
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
        return $this->id;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    final public function getUser(): string
    {
        return $this->user ?? getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? getCurrentGroup();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
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
