<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal\Logger;

use PHPStreamServer\Core\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final class NullLogger implements LoggerInterface
{
    use LoggerTrait;

    public function withChannel(string $channel): LoggerInterface
    {
        return $this;
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        // no action
    }
}
