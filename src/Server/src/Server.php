<?php

declare(strict_types=1);

namespace PHPStreamServer;

use Composer\InstalledVersions;
use PHPStreamServer\Internal\Console\App;
use PHPStreamServer\Plugin\Plugin;
use PHPStreamServer\Plugin\System\SystemPlugin;
use PHPStreamServer\SupervisorPlugin\SupervisorPlugin;
use Revolt\EventLoop;

final class Server
{
    public const PACKAGE = 'phpstreamserver/phpstreamserver';
    public const NAME = 'PHPStreamServer';
    public const SHORTNAME = 'phpss';
    public const TITLE = '🌸 PHPStreamServer - PHP application server';

    /** @var array<Plugin> */
    private array $plugins = [];

    /** @var array<Process> */
    private array $workers = [];

    public function __construct(
        private string|null $pidFile = null,
        private string|null $socketFile = null,
        int $stopTimeout = 10,
        float $restartDelay = 0.25,
    ) {
        $this->pidFile ??= getDefaultPidFile();
        $this->socketFile ??= getDefaultSocketFile();
        $this->addPlugin(new SystemPlugin());
        $this->addPlugin(new SupervisorPlugin($stopTimeout, $restartDelay));
    }

    public function addPlugin(Plugin ...$plugins): self
    {
        \array_push($this->plugins, ...$plugins);

        return $this;
    }

    public function addWorker(Process ...$workers): self
    {
        \array_push($this->workers, ...$workers);

        return $this;
    }

    public function run(): int
    {
        $app = new App(...\array_merge(...\array_map(static fn (Plugin $p) => $p->registerCommands(), $this->plugins)));
        $map = new \WeakMap();
        $map[EventLoop::getDriver()] = \get_object_vars($this);
        unset($this->workers, $this->plugins);
        return $app->run($map);
    }

    public static function getVersion(): string
    {
        static $version;
        try {
            return $version ??= InstalledVersions::getVersion(self::PACKAGE) ?? '???';
        } catch (\OutOfBoundsException) {
            return $version ??= '???';
        }
    }

    public static function getVersionString(): string
    {
        return \sprintf('%s/%s', \strtolower(self::NAME), self::getVersion());
    }
}
