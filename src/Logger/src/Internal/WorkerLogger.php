<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal;

use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\MessageBus\Message\CompositeMessage;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\ContextFlattenNormalizer;
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
    private array $log = [];
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
        $event = new LogEntry(
            time: new \DateTimeImmutable('now'),
            pid: \posix_getpid(),
            level: LogLevel::fromName((string) $level),
            channel: $this->channel,
            message: (string) $message,
            context: ContextFlattenNormalizer::flatten($context),
        );

        $this->log[] = $event;

        if ($this->callbackId === '') {
            $this->callbackId = EventLoop::defer(fn() => $this->flush());
        }
    }

    private function flush(): void
    {
        $log = $this->log;
        $this->log = [];
        $this->callbackId = '';
        $this->messageBus->dispatch(new CompositeMessage($log));
    }
}
