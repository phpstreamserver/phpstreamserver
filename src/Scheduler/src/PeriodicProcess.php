<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler;

use PHPStreamServer\Core\Exception\UserChangeException;
use PHPStreamServer\Core\Internal\ErrorHandler;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Core\Worker\ContainerInterface;
use PHPStreamServer\Core\Worker\ProcessUserChange;
use PHPStreamServer\Core\Worker\Status;
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
    public readonly ContainerInterface $container;
    public readonly LoggerInterface $logger;
    public readonly MessageBusInterface $bus;

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
        public readonly string $name = 'none',
        public readonly string $schedule = '1 minute',
        public readonly int $jitter = 0,
        private string|null $user = null,
        private string|null $group = null,
        private \Closure|null $onStart = null,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
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

        ErrorHandler::register($this->logger);
        EventLoop::setErrorHandler(function (\Throwable $exception) {
            ErrorHandler::handleException($exception);
            $this->exitCode = 1;
        });

        try {
            $this->setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), [(new \ReflectionObject($this))->getShortName() => $this->name]);
        }

        EventLoop::unreference(EventLoop::onSignal(SIGINT, static fn() => null));

        EventLoop::queue(function () {
            if ($this->onStart !== null) {
                ($this->onStart)($this);
            }
        });

        EventLoop::run();

        return $this->exitCode;
    }

    public static function handleBy(): array
    {
        return [SchedulerPlugin::class];
    }

    final public function getUser(): string
    {
        return $this->user ?? getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? getCurrentGroup();
    }
}
