<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\ReloadStrategy;

use Amp\Http\Server\Request;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal\ReloadStrategy\ReloadStrategyInterface;

/**
 * Reload worker on every $maxRequests requests.
 * To prevent simultaneous restart of all workers $dispersionPercentage can be set.
 * 1000 $maxRequests and 20% $dispersionPercentage will restart between 800 and 1000
 */
final class MaxRequestsReloadStrategy implements ReloadStrategyInterface
{
    private int $requestsCount = 0;
    private readonly int $maxRequests;

    public function __construct(int $maxRequests, int $dispersionPercentage = 0)
    {
        $minRequests = $maxRequests - (int) \round($maxRequests * $dispersionPercentage / 100);
        $this->maxRequests = \random_int($minRequests, $maxRequests);
    }

    public function shouldReload(mixed $eventObject = null): bool
    {
        return $eventObject instanceof Request && ++$this->requestsCount > $this->maxRequests;
    }
}