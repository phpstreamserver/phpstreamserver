<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

use PHPStreamServer\Core\MessageBus\AllowedClassesProviderInterface;
use PHPStreamServer\Core\MessageBus\AuthorizedSources;
use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Core\MessageBus\MessageSource;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenDateTime;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenEnum;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenException;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenObject;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenResource;

/**
 * @implements MessageInterface<null>
 */
#[AuthorizedSources(MessageSource::MASTER, MessageSource::CHILD)]
final readonly class LogEntry implements MessageInterface, AllowedClassesProviderInterface
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
            0 => $this->time->format('Uu'),
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
        $this->time = \DateTimeImmutable::createFromFormat('U.u', \substr($data[0], 0, -6) . '.' . \substr($data[0], -6));
        $this->pid = $data[1];
        $this->level = LogLevel::from($data[2]);
        $this->channel = $data[3];
        $this->message = $data[4];
        $this->context = $data[5];
    }

    public static function getAllowedClasses(): array
    {
        return [
            FlattenDateTime::class,
            FlattenEnum::class,
            FlattenException::class,
            FlattenObject::class,
            FlattenResource::class,
        ];
    }
}
