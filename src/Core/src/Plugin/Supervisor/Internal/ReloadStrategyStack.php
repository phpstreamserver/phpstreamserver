<?php

declare(strict_types=1);

namespace PHPStreamServer\Core\Plugin\Supervisor\Internal;

use PHPStreamServer\Core\Plugin\Supervisor\ReloadStrategy\ReloadStrategy;
use PHPStreamServer\Core\Plugin\Supervisor\ReloadStrategy\TimerReloadStrategy;
use Revolt\EventLoop;

/**
 * @internal
 */
final class ReloadStrategyStack
{
    /** @var list<ReloadStrategy> */
    private array $reloadStrategies = [];

    /**
     * @param array<ReloadStrategy> $reloadStrategies
     */
    public function __construct(private readonly \Closure $reloadCallback, array $reloadStrategies = [])
    {
        $this->addReloadStrategy(...$reloadStrategies);
    }

    public function __invoke(mixed $event): void
    {
        $this->emitEvent($event);
    }

    public function addReloadStrategy(ReloadStrategy ...$reloadStrategies): void
    {
        foreach ($reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy instanceof TimerReloadStrategy) {
                EventLoop::repeat($reloadStrategy->getInterval(), function () use ($reloadStrategy): void {
                    if ($reloadStrategy->shouldReload()) {
                        $this->reload();
                    }
                });
            } else {
                $this->reloadStrategies[] = $reloadStrategy;
            }
        }
    }

    /**
     * @param mixed $event any value that checked by reload strategies. Could be exception, request etc.
     */
    public function emitEvent(mixed $event): void
    {
        foreach ($this->reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy->shouldReload($event)) {
                $this->reload();
                break;
            }
        }
    }

    private function reload(): void
    {
        EventLoop::defer(function (): void {
            ($this->reloadCallback)();
        });
    }
}
