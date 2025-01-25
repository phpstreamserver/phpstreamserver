<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<bool>
 * @internal
 */
final readonly class RegisterMetricMessage implements MessageInterface
{
    private function __construct(
        public string $type,
        public string $namespace,
        public string $name,
        public string $help,
        public array $labels,
        public array|null $buckets = null,
    ) {
    }

    public static function counter(string $namespace, string $name, string $help, array $labels): self
    {
        return new self('counter', $namespace, $name, $help, $labels);
    }

    public static function gauge(string $namespace, string $name, string $help, array $labels): self
    {
        return new self('gauge', $namespace, $name, $help, $labels);
    }

    public static function histogram(string $namespace, string $name, string $help, array $labels, array $buckets): self
    {
        return new self('histogram', $namespace, $name, $help, $labels, $buckets);
    }

    public static function summary(string $namespace, string $name, string $help, array $labels, array|null $buckets = null): self
    {
        return new self('summary', $namespace, $name, $help, $labels, $buckets);
    }
}
