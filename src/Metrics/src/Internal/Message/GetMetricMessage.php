<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<GetMetricResponse|false>
 * @internal
 */
final readonly class GetMetricMessage implements MessageInterface
{
    private function __construct(
        public string $type,
        public string $namespace,
        public string $name,
    ) {
    }

    public static function counter(string $namespace, string $name): self
    {
        return new self('counter', $namespace, $name);
    }

    public static function gauge(string $namespace, string $name): self
    {
        return new self('gauge', $namespace, $name);
    }

    public static function histogram(string $namespace, string $name): self
    {
        return new self('histogram', $namespace, $name);
    }

    public static function summary(string $namespace, string $name): self
    {
        return new self('summary', $namespace, $name);
    }
}
