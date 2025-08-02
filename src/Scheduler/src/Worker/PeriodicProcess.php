<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Worker;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\Exception\UserChangeException;
use PHPStreamServer\Core\Internal\ErrorHandler;
use PHPStreamServer\Core\Internal\ProcessUserChange;
use PHPStreamServer\Core\Internal\Status;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

use function PHPStreamServer\Core\getCurrentGroup;
use function PHPStreamServer\Core\getCurrentUser;

class PeriodicProcess implements Process
{
    use ProcessUserChange;

    private Status $status = Status::SHUTDOWN;
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
     *  - Number of seconds
     *  - An ISO8601 datetime format
     *  - An ISO8601 duration format
     *  - A relative date format as supported by \DateInterval
     *  - A cron expression
     *
     * @param string $schedule Schedule in one of the formats described above
     * @param int $jitter Jitter in seconds that adds a random time offset to the schedule
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
        static $nextId = 0;
        $this->id = ++$nextId;

        if ($name === '') {
            $this->name = 'periodic_worker_' . $this->id;
        } else {
            $this->name = $name;
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
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: perriodic process  %s', Server::NAME, $this->name));
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
            $this->setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), [(new \ReflectionObject($this))->getShortName() => $this->name]);
        }

        EventLoop::unreference(EventLoop::onSignal(SIGINT, static fn() => null));

        EventLoop::queue(function () {
            foreach ($this->onStartCallbacks as $onStartCallback) {
                $onStartCallback($this);
            }
        });

        EventLoop::run();

        return $this->exitCode;
    }

    public static function handleBy(): array
    {
        return [SchedulerPlugin::class];
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
        $this->onStartCallbacks[((string) $priority) . \uniqid()] = $onStart;
        \ksort($this->onStartCallbacks, SORT_NUMERIC);
    }

    public function setExitCode(int $exitCode): void
    {
        $this->exitCode = $exitCode;
    }
}
