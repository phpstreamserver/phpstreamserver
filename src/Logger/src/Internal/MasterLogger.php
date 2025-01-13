<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal;

use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Plugin\Logger\Handler;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\ContextFlattenNormalizer;
use PHPStreamServer\Plugin\Logger\LogLevel;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final class MasterLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var array<Handler>
     */
    private array $handlers = [];
    private string $channel = 'server';

    public function __construct()
    {
    }

    public function addHandler(Handler $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function withChannel(string $channel): self
    {
        $that = clone $this;
        $that->channel = $channel;

        return $that;
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->logEntry(new LogEntry(
            time: new \DateTimeImmutable('now'),
            pid: \posix_getpid(),
            level: LogLevel::fromName((string) $level),
            channel: $this->channel,
            message: (string) $message,
            context: ContextFlattenNormalizer::flatten($context),
        ));
    }

    public function logEntry(LogEntry $logEntry): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($logEntry)) {
                $handler->handle($logEntry);
            }
        }
    }
}
