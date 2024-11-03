<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\MessageBus\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * @implements Message<null>
 */
final readonly class ContainerSetCommand implements Message
{
    public function __construct(public string $id, public mixed $value)
    {
    }
}