<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal;

use PHPStreamServer\Plugin\FileMonitor\Internal\FFIBindings\Inotify;
use PHPStreamServer\Plugin\FileMonitor\WatchRule;
use Revolt\EventLoop;

/**
 * @internal
 */
final class InotifyFileWatcher extends AbstractFileWatcher
{
    private Inotify $inotify;

    private string $fdCallbackId = '';

    /**
     * @var array<int, string>
     */
    private array $pathByWd = [];

    /**
     * @var array<string, list<WatchRule>>
     */
    private array $rulesBySourceDir = [];

    protected static function getReloadDelay(): float
    {
        return 0.15;
    }

    public function start(): void
    {
        $this->inotify = new Inotify();

        foreach ($this->rules as $rule) {
            $this->rulesBySourceDir[$rule->sourceDir][] = $rule;
        }

        foreach ($this->rulesBySourceDir as $sourceDir => $rules) {
            if (!\is_dir($sourceDir)) {
                continue;
            }

            $this->watchDir(\dirname($sourceDir));
            $this->watchSource($sourceDir, $rules);
        }

        $this->fdCallbackId = EventLoop::onReadable($this->inotify->getStream(), fn() => $this->onNotify());
    }

    public function stop(): void
    {
        parent::stop();
        if ($this->fdCallbackId !== '') {
            EventLoop::cancel($this->fdCallbackId);
            $this->fdCallbackId = '';
        }
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->inotify)) {
            $this->inotify->close();
            unset($this->inotify);
        }
    }

    private function onNotify(): void
    {
        foreach ($this->inotify->read() as $event) {
            $isIgnored = ($event['mask'] & Inotify::IN_IGNORED) !== 0;
            $isDirectory = ($event['mask'] & Inotify::IN_ISDIR) !== 0;
            $isCreatedDirectory = $isDirectory && ($event['mask'] & Inotify::IN_CREATE) !== 0;
            $isMovedFromDirectory = $isDirectory && ($event['mask'] & Inotify::IN_MOVED_FROM) !== 0;
            $isMovedToDirectory = $isDirectory && ($event['mask'] & Inotify::IN_MOVED_TO) !== 0;

            if ($isIgnored) {
                unset($this->pathByWd[$event['wd']]);
                continue;
            }

            if (!isset($this->pathByWd[$event['wd']])) {
                continue;
            }

            $parentPath = $this->pathByWd[$event['wd']];
            $path = \rtrim($parentPath, '/') . '/' . $event['name'];
            $recursiveRules = $isDirectory ? $this->getRecursiveRules($parentPath) : [];
            $sourceRules = $this->rulesBySourceDir[$path] ?? [];

            if ($sourceRules !== [] && ($event['mask'] & (Inotify::IN_DELETE | Inotify::IN_MOVED_FROM)) !== 0) {
                $this->unwatchDir($path);

                foreach ($sourceRules as $rule) {
                    $this->scheduleReload($rule->invalidateOpcache);
                }
            }

            if ($sourceRules !== [] && ($event['mask'] & (Inotify::IN_CREATE | Inotify::IN_MOVED_TO)) !== 0 && \is_dir($path)) {
                $this->unwatchDir($path);
                $this->watchSource($path, $sourceRules);

                foreach ($sourceRules as $rule) {
                    $this->scheduleReload($rule->invalidateOpcache);
                }
            }

            if ($isMovedFromDirectory) {
                $this->unwatchDir($path);

                foreach ($recursiveRules as $rule) {
                    $this->scheduleReload($rule->invalidateOpcache);
                }
            }

            if ($isCreatedDirectory || $isMovedToDirectory) {
                $matchingRules = $recursiveRules === [] ? [] : $this->watchTree($path, $recursiveRules);

                foreach ($matchingRules as $rule) {
                    $this->scheduleReload($rule->invalidateOpcache);
                }

                if ($isCreatedDirectory) {
                    continue;
                }
            }

            foreach ($this->rules as $rule) {
                if ($this->isPatternMatches($rule, $path)) {
                    $this->scheduleReload($rule->invalidateOpcache);
                }
            }
        }
    }

    /**
     * @param list<WatchRule> $rules
     */
    private function watchSource(string $path, array $rules): void
    {
        foreach ($rules as $rule) {
            if ($rule->recursive) {
                $this->watchTree($path);
                return;
            }
        }

        $this->watchDir($path);
    }

    /**
     * @param list<WatchRule> $rules
     * @return list<WatchRule>
     */
    private function watchTree(string $path, array $rules = []): array
    {
        if (!$this->watchDir($path)) {
            return [];
        }

        $remainingRules = [];
        foreach ($rules as $rule) {
            $remainingRules[\spl_object_id($rule)] = $rule;
        }
        $matchingRules = [];

        $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                $this->watchDir($file->getPathname());
                continue;
            }

            if ($remainingRules === [] || !$file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();
            foreach ($remainingRules as $ruleId => $rule) {
                if ($this->isPatternMatches($rule, $filePath)) {
                    $matchingRules[] = $rule;
                    unset($remainingRules[$ruleId]);
                }
            }
        }

        return $matchingRules;
    }

    private function watchDir(string $path): bool
    {
        if (!\is_dir($path)) {
            return false;
        }

        $wd = $this->inotify->addWatch($path, Inotify::IN_MODIFY | Inotify::IN_CREATE | Inotify::IN_DELETE | Inotify::IN_MOVED_FROM | Inotify::IN_MOVED_TO);
        $this->pathByWd[$wd] = $path;

        return true;
    }

    private function unwatchDir(string $path): void
    {
        foreach ($this->pathByWd as $wd => $watchedPath) {
            if ($watchedPath !== $path && !\str_starts_with($watchedPath, $path . '/')) {
                continue;
            }

            $this->inotify->removeWatch($wd);
            unset($this->pathByWd[$wd]);
        }
    }

    /**
     * @return list<WatchRule>
     */
    private function getRecursiveRules(string $path): array
    {
        $rules = [];

        foreach ($this->rules as $rule) {
            if ($rule->recursive && ($path === $rule->sourceDir || \str_starts_with($path, \rtrim($rule->sourceDir, '/') . '/'))) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }
}
