<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\Pipeline\Queue;
use Revolt\EventLoop;
use Revolt\EventLoop\FiberLocal;

/**
 * @implements \IteratorAggregate<string>
 */
final class OutputStreamIterator implements \IteratorAggregate
{
    /** @var FiberLocal<Queue|null> */
    private static FiberLocal $fiberLocal;

    public function __construct(private readonly \Closure $closure)
    {
        if (isset(self::$fiberLocal)) {
            return;
        }

        /** @var FiberLocal<Queue|null> */
        self::$fiberLocal = new FiberLocal(static fn(): null => null);

        \ob_start(static function (string $chunk, int $phase): string {
            $queue = self::$fiberLocal->get();

            if ($queue === null) {
                return $chunk;
            }

            $isWrite = ($phase & \PHP_OUTPUT_HANDLER_WRITE) === \PHP_OUTPUT_HANDLER_WRITE;
            if ($isWrite && $chunk !== '') {
                $queue->push($chunk);
            }

            return '';
        }, 1);
    }

    public function getIterator(): \Traversable
    {
        $closure = $this->closure;
        $queue = new Queue();

        EventLoop::queue(static function () use ($closure, $queue): void {
            self::$fiberLocal->set($queue);
            try {
                $closure();
            } finally {
                $queue->complete();
            }
        });

        return $queue->iterate();
    }
}
