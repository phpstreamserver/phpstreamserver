<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal\Message;

/**
 * @internal
 */
final readonly class GetMetricResponse
{
    public function __construct(
        public string $type,
        public string $namespace,
        public string $name,
        public string $help,
        public array $labels,
    ) {
    }
}
