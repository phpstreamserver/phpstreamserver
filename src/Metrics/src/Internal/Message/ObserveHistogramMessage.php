<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal\Message;

use PHPStreamServer\Core\MessageBus\AuthorizedSources;
use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Core\MessageBus\MessageSource;

/**
 * @implements MessageInterface<null>
 * @internal
 */
#[AuthorizedSources(MessageSource::MASTER, MessageSource::CHILD)]
final readonly class ObserveHistogramMessage implements MessageInterface
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
