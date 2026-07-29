<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\ReloadStrategy;

use Amp\Http\Server\Request;
use PHPStreamServer\Core\ReloadStrategy\ReloadStrategy;

/**
 * Reloads the worker after it handles $maxRequests requests.
 * To prevent all workers from restarting at the same time, set $dispersionPercentage.
 * With $maxRequests = 1000 and $dispersionPercentage = 20, each worker restarts after a random number of requests between 800 and 1000.
 */
final class MaxRequestsReloadStrategy implements ReloadStrategy
{
    private int $requestCount = 0;
    private readonly int $minRequests;
    private int|null $requestLimit = null;

    public function __construct(private readonly int $maxRequests, int $dispersionPercentage = 0)
    {
        $this->minRequests = $maxRequests - (int) \round($maxRequests * $dispersionPercentage * 0.01);
    }

    public function shouldReload(mixed $eventObject = null): bool
    {
        if (!$eventObject instanceof Request) {
            return false;
        }

        if ($this->requestLimit === null) {
            $this->requestLimit = \random_int($this->minRequests, $this->maxRequests);
        }

        return ++$this->requestCount >= $this->requestLimit;
    }
}
