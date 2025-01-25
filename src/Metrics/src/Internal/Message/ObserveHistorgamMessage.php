<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 * @internal
 */
final readonly class ObserveHistorgamMessage implements MessageInterface
{
    /**
     * @param array<string, string> $labels
     * @param list<float> $values
     */
    public function __construct(
        public string $namespace,
        public string $name,
        public array $labels,
        public array $values,
    ) {
    }
}
