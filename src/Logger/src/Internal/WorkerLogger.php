<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal;

use PHPStreamServer\Core\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final class WorkerLogger implements LoggerInterface
{
    use LoggerTrait;

    private string $channel = 'worker';

    private WorkerLogDispatcher $workerLogDispatcher;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->workerLogDispatcher = new WorkerLogDispatcher($messageBus);
    }

    public function withChannel(string $channel): self
    {
        $that = clone $this;
        $that->channel = $channel;

        return $that;
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->workerLogDispatcher->log((string) $level, $this->channel, $message, $context);
    }
}
