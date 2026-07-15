<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler;

use Amp\Future;
use PHPStreamServer\Core\Exception\ServiceNotFoundException;
use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use PHPStreamServer\Plugin\Scheduler\Command\SchedulerCommand;
use PHPStreamServer\Plugin\Scheduler\Internal\MetricsHandler;
use PHPStreamServer\Plugin\Scheduler\Internal\Scheduler;
use PHPStreamServer\Plugin\Scheduler\Status\SchedulerStatus;
use PHPStreamServer\Plugin\Scheduler\Worker\PeriodicProcess;
use Revolt\EventLoop\Suspension;

/**
 * @extends Plugin<PeriodicProcess>
 */
final class SchedulerPlugin extends Plugin
{
    private Scheduler $scheduler;
    private MessageHandlerInterface $handler;

    public function __construct()
    {
    }

    protected function beforeStart(): void
    {
        /** @var int $stopTimeout */
        $stopTimeout = $this->masterContainer->getParameter('stop_timeout');
        $this->scheduler = new Scheduler($stopTimeout);
    }

    public function registerWorker(Process $worker): void
    {
        $this->scheduler->registerWorker($worker);
    }

    public function onStart(): void
    {
        $this->masterContainer->setService(SchedulerStatus::class, $this->scheduler->schedulerStatus);

        /** @var Suspension $suspension */
        $suspension = $this->masterContainer->getService('main_suspension');
        $logger = &$this->masterContainer->getService(LoggerInterface::class);
        $bus = &$this->masterContainer->getService(MessageBusInterface::class);
        $this->handler = &$this->masterContainer->getService(MessageHandlerInterface::class);

        $this->scheduler->start($suspension, $logger, $bus, $this->handler);
    }

    public function afterStart(): void
    {
        if (\interface_exists(RegistryInterface::class)) {
            try {
                $registry = $this->masterContainer->getService(RegistryInterface::class);
                $this->masterContainer->setService(MetricsHandler::class, new MetricsHandler($registry, $this->scheduler->schedulerStatus, $this->handler));
            } catch (ServiceNotFoundException) {
                // no action
            }
        }
    }

    public function onStop(): Future
    {
        return $this->scheduler->stop();
    }

    public function registerCommands(): iterable
    {
        return [
            new SchedulerCommand(),
        ];
    }
}
