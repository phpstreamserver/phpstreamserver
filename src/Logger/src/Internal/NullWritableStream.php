<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal;

use Amp\ByteStream\WritableStream;

/**
 * @internal
 */
final class NullWritableStream implements WritableStream
{
    public function close(): void
    {
    }

    public function isClosed(): bool
    {
        return false;
    }

    public function onClose(\Closure $onClose): void
    {
    }

    public function write(string $bytes): void
    {
    }

    public function end(): void
    {
    }

    public function isWritable(): bool
    {
        return true;
    }
}
