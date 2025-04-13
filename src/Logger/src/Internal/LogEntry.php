<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal;

use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Plugin\Logger\LogLevel;

/**
 * @implements MessageInterface<null>
 * @internal
 */
final readonly class LogEntry implements MessageInterface
{
    public function __construct(
        public \DateTimeImmutable $time,
        public int $pid,
        public LogLevel $level,
        public string $channel,
        public string $message,
        public array $context = [],
    ) {
    }

    public function __serialize(): array
    {
        return [
            0 => $this->time->getTimestamp(),
            1 => $this->pid,
            2 => $this->level->value,
            3 => $this->channel,
            4 => $this->message,
            5 => $this->context,
        ];
    }

    public function __unserialize(array $data): void
    {
        /** @psalm-suppress PossiblyFalsePropertyAssignmentValue */
        $this->time = \DateTimeImmutable::createFromFormat('U', (string) $data[0]);
        $this->pid = $data[1];
        $this->level = LogLevel::from($data[2]);
        $this->channel = $data[3];
        $this->message = $data[4];
        $this->context = $data[5];
    }
}
