<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal;

use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\Message\CompositeMessage;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\ContextFlattenNormalizer;
use PHPStreamServer\Plugin\Logger\LogLevel;
use Psr\Log\LoggerTrait;
use Revolt\EventLoop;

/**
 * @internal
 */
final class WorkerLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var list<LogEntry>
     */
    private array $logs = [];
    private string $channel = 'worker';
    private string $callbackId = '';

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function withChannel(string $channel): self
    {
        $that = clone $this;
        $that->channel = $channel;

        return $that;
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = new LogEntry(
            time: new \DateTimeImmutable('now'),
            pid: \posix_getpid(),
            level: LogLevel::fromString((string) $level),
            channel: $this->channel,
            message: (string) $message,
            context: ContextFlattenNormalizer::flatten($context),
        );

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
            $bus->dispatch(new CompositeMessage($logsToSend));
        });
    }
}
