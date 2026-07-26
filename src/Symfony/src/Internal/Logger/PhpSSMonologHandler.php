<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal\Logger;

use Monolog\Handler\AbstractHandler;
use Monolog\LogRecord;
use PHPStreamServer\Core\Exception\ServerIsNotRunning;
use PHPStreamServer\Core\MessageBus\CompositeMessage;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Plugin\Logger\ContextFlattenNormalizer;
use PHPStreamServer\Plugin\Logger\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;

/**
 * @internal
 */
final class PhpSSMonologHandler extends AbstractHandler
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    /**
     * @param array<LogRecord> $records
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function handleBatch(array $records): void
    {
        $buffer = [];

        foreach ($records as $record) {
            $buffer[] = new LogEntry(
                time: $record->datetime,
                pid: \posix_getpid(),
                level: LogLevel::fromRFC5424($record->level->toRFC5424Level()),
                channel: $record->channel,
                message: $record->message,
                context: ContextFlattenNormalizer::flatten($record->context),
            );
        }

        try {
            $this->bus->dispatch(new CompositeMessage($buffer));
        } catch (ServerIsNotRunning) {
            // ignore
        }
    }

    public function handle(LogRecord $record): bool
    {
        $this->handleBatch([$record]);

        return !$this->bubble;
    }
}
