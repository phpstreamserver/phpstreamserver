<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal\Logger;

use Amp\Future;
use Monolog\Handler\AbstractHandler;
use Monolog\LogRecord;
use PHPStreamServer\Core\Exception\ServerIsNotRunning;
use PHPStreamServer\Core\MessageBus\CompositeMessage;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Logger\ContextFlattenNormalizer;
use PHPStreamServer\Plugin\Logger\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;
use Revolt\EventLoop;

/**
 * @internal
 */
final class PhpSSMonologHandler extends AbstractHandler
{
    private const MAX_BATCH_SIZE = 50;

    /**
     * @var list<LogEntry>
     */
    private array $buffer = [];
    private string $callbackId = '';
    private Future|null $lastDispatch = null;

    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    /**
     * @param array<LogRecord> $records
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function handleBatch(array $records): void
    {
        if ($records === []) {
            return;
        }

        if (!$this->buffer($records)) {
            return;
        }

        $this->flush();
    }

    public function handle(LogRecord $record): bool
    {
        return $this->buffer([$record]) && !$this->bubble;
    }

    /**
     * @param array<LogRecord> $records
     */
    private function buffer(array $records): bool
    {
        $handled = false;

        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                continue;
            }

            $handled = true;
            $this->buffer[] = new LogEntry(
                time: $record->datetime,
                pid: \posix_getpid(),
                level: LogLevel::fromRFC5424($record->level->toRFC5424Level()),
                channel: $record->channel,
                message: $record->message,
                context: ContextFlattenNormalizer::flatten($record->context),
            );

            if (\count($this->buffer) >= self::MAX_BATCH_SIZE) {
                $this->flush();
            }
        }

        if ($handled && $this->buffer !== [] && $this->callbackId === '') {
            $this->callbackId = EventLoop::defer(function (): void {
                $this->callbackId = '';
                $this->flush();
            });
        }

        return $handled;
    }

    public function reset(): void
    {
        $this->flush();
    }

    public function close(): void
    {
        $this->flush();

        try {
            $this->lastDispatch?->await();
        } catch (\Throwable) {
            // ignore
        }

        $this->lastDispatch = null;
    }

    private function flush(): void
    {
        if ($this->callbackId !== '') {
            EventLoop::cancel($this->callbackId);
            $this->callbackId = '';
        }

        if ($this->buffer === []) {
            return;
        }

        $buffer = $this->buffer;
        $this->buffer = [];

        try {
            $this->lastDispatch = $this->bus->dispatch(new CompositeMessage($buffer))->ignore();
        } catch (ServerIsNotRunning) {
            // ignore
        }
    }
}
