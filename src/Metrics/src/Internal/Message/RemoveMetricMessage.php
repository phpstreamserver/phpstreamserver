<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 * @internal
 */
final readonly class RemoveMetricMessage implements MessageInterface
{
    public function __construct(
        public string $type,
        public string $namespace,
        public string $name,
        public array $labels,
    ) {
    }
}
