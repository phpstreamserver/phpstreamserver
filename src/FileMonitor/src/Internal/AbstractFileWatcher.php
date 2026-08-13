<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use Revolt\EventLoop;
use Webmozart\Glob\Glob;

/**
 * @internal
 */
abstract class AbstractFileWatcher
{
    protected const RELOAD_DELAY = 0.25;

    protected readonly string $sourceDir;
    private readonly string $globRegex;
    protected readonly bool $recursive;
    private string $delayedReloadCallbackId = '';

    public function __construct(string $glob, private readonly \Closure $reloadCallback)
    {
        $this->sourceDir = Glob::getBasePath($glob);
        $this->globRegex = Glob::toRegEx($glob);
        $this->recursive = \dirname($glob) !== $this->sourceDir;
    }

    protected function isPatternMatches(string $path): bool
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return \preg_match($this->globRegex, $path) === 1;
    }

    protected function scheduleReload(): void
    {
        if ($this->delayedReloadCallbackId !== '') {
            return;
        }

        $reloadCallback = $this->reloadCallback;
        $delayedReloadCallbackId = &$this->delayedReloadCallbackId;
        $this->delayedReloadCallbackId = EventLoop::delay(self::RELOAD_DELAY, static function () use ($reloadCallback, &$delayedReloadCallbackId): void {
            $delayedReloadCallbackId = '';
            ($reloadCallback)();
        });
        EventLoop::unreference($this->delayedReloadCallbackId);
    }

    abstract public function start(): void;

    abstract public function stop(): void;
}
