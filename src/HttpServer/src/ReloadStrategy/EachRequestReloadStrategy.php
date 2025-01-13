<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\ReloadStrategy;

use Amp\Http\Server\Request;
use PHPStreamServer\Core\ReloadStrategy\ReloadStrategy;

/**
 * Reload worker after each request.
 */
final class EachRequestReloadStrategy implements ReloadStrategy
{
    public function shouldReload(mixed $eventObject = null): bool
    {
        return $eventObject instanceof Request;
    }
}
