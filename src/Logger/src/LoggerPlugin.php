<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\Logger\LoggerInterface as CoreLoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use PHPStreamServer\Plugin\Logger\Internal\MasterLogger;
use PHPStreamServer\Plugin\Logger\Internal\WorkerLogger;
use Revolt\EventLoop;

final class LoggerPlugin extends Plugin
{
    /**
     * @var array<Handler>
     */
    private array $handlers;

    public function __construct(Handler ...$handlers)
    {
        $this->handlers = $handlers;
    }

    public function onStart(): void
    {
        $masterLogger = new MasterLogger();

        $workerLoggerFactory = static function (ContainerInterface $container): WorkerLogger {
            return new WorkerLogger($container->getService(MessageBusInterface::class));
        };

        $this->masterContainer->setService(CoreLoggerInterface::class, $masterLogger);
        $this->workerContainer->registerService(CoreLoggerInterface::class, $workerLoggerFactory);
        $this->workerContainer->setAlias(LoggerInterface::class, CoreLoggerInterface::class);

        $messageBusHandler = $this->masterContainer->getService(MessageHandlerInterface::class);

        foreach ($this->handlers as $loggerHandler) {
            $loggerHandler
                ->start()
                ->map(static function () use ($masterLogger, $loggerHandler): void {
                    $masterLogger->addHandler($loggerHandler);
                })
                ->catch(static function (\Throwable $e) use ($masterLogger): void {
                    $masterLogger->error($e->getMessage(), ['exception' => $e]);
                })
            ;
        }

        $messageBusHandler->subscribe(LogEntry::class, static function (LogEntry $event) use ($masterLogger): void {
            EventLoop::queue(static function () use ($event, $masterLogger): void {
                $masterLogger->logEntry($event);
            });
        });
    }
}
