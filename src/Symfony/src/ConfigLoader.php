<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class ConfigLoader implements CacheWarmerInterface
{
    private array $config = [];
    private ConfigCache $cache;

    public function __construct(private string $projectDir, string $cacheDir, bool $isDebug)
    {
        $this->cache = new ConfigCache(\sprintf('%s/phpss_config.cache.php', $cacheDir), $isDebug);
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, string $buildDir = null): array
    {
        $packagesDir = \sprintf('%s/config/packages', $this->projectDir);
        $metadata = \is_dir($packagesDir) ? [new DirectoryResource($packagesDir, '/phpstreamserver/')] : [];
        $this->cache->write(\sprintf('<?php return %s;', \var_export($this->config, true)), $metadata);

        return [];
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getConfig(KernelFactory|null $kernelFactory = null): array
    {
        // Warm up cache if no fresh config found (do it in a forked process as the main process should not boot kernel)
        if ($this->cache->isFresh() === false && $kernelFactory !== null) {
            if (\pcntl_fork() === 0) {
                $kernelFactory->createKernel()->boot();
                exit;
            } else {
                \pcntl_wait($status);
                unset($status);
            }
        }

        return require $this->cache->getPath();
    }
}
