<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use PHPStreamServer\Plugin\FileMonitor\WatchRule;
use Revolt\EventLoop;

/**
 * @internal
 */
abstract class AbstractFileWatcher
{
    private string $delayedReloadCallbackId = '';
    private bool $pendingInvalidateOpcache = false;

    /**
     * @param list<WatchRule> $rules
     * @param \Closure(bool): void $reloadCallback
     */
    final public function __construct(protected readonly array $rules, private readonly \Closure $reloadCallback)
    {
    }

    protected function isPatternMatches(WatchRule $rule, string $path): bool
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return \preg_match($rule->globRegex, $path) === 1;
    }

    protected function scheduleReload(bool $invalidateOpcache): void
    {
        $this->pendingInvalidateOpcache = $this->pendingInvalidateOpcache || $invalidateOpcache;

        if ($this->delayedReloadCallbackId !== '') {
            return;
        }

        $callback = function (): void {
            $invalidateOpcache = $this->pendingInvalidateOpcache;
            $this->delayedReloadCallbackId = '';
            $this->pendingInvalidateOpcache = false;
            ($this->reloadCallback)($invalidateOpcache);
        };

        $reloadDelay = static::getReloadDelay();
        if ($reloadDelay <= 0) {
            $this->delayedReloadCallbackId = EventLoop::defer($callback);
        } else {
            $this->delayedReloadCallbackId = EventLoop::delay($reloadDelay, $callback);
        }
    }

    abstract protected static function getReloadDelay(): float;

    abstract public function start(): void;

    public function stop(): void
    {
        if ($this->delayedReloadCallbackId !== '') {
            EventLoop::cancel($this->delayedReloadCallbackId);
            $this->delayedReloadCallbackId = '';
            $this->pendingInvalidateOpcache = false;
        }
    }
}
