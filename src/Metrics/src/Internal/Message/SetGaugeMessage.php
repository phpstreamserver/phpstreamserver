<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 * @internal
 */
final readonly class SetGaugeMessage implements MessageInterface
{
    public function __construct(
        public string $namespace,
        public string $name,
        public array $labels,
        public float $value,
        public bool $increase = false,
    ) {
    }
}
