<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal;

use PHPStreamServer\Core\MessageBus\CompositeMessage;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Logger\ContextFlattenNormalizer;
use PHPStreamServer\Plugin\Logger\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;
use Revolt\EventLoop;

/**
 * @internal
 */
final class WorkerLogDispatcher
{
    /**
     * @var list<LogEntry>
     */
    private array $logs = [];
    private string $callbackId = '';
    private int $driverId;

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
        $this->driverId = \spl_object_id(EventLoop::getDriver());
    }

    public function log(string $level, string $channel, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = new LogEntry(
            time: new \DateTimeImmutable('now'),
            pid: \posix_getpid(),
            level: LogLevel::fromString($level),
            channel: $channel,
            message: (string) $message,
            context: ContextFlattenNormalizer::flatten($context),
        );

        $driverId = \spl_object_id(EventLoop::getDriver());
        if ($this->driverId !== $driverId) {
            if ($this->callbackId !== '') {
                $this->callbackId = '';
            }
            $this->driverId = $driverId;
            $logsToSend = $this->logs;
            $this->logs = [];
            try {
                $this->messageBus->dispatch(new CompositeMessage($logsToSend))->await();
            } catch (\Throwable) {
                // noop
            }
            return;
        }

        if ($this->callbackId !== '') {
            return;
        }

        $bus = $this->messageBus;
        $logs = &$this->logs;
        $callbackId = &$this->callbackId;

        $callbackId = EventLoop::defer(static function () use ($bus, &$logs, &$callbackId): void {
            $logsToSend = $logs;
            $logs = [];
            $callbackId = '';
            $bus->dispatch(new CompositeMessage($logsToSend))->ignore();
        });
    }
}
