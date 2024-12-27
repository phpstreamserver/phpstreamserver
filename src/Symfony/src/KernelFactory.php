<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle;

use Symfony\Component\HttpKernel\KernelInterface;

final readonly class KernelFactory
{
    private string $environment;
    private bool $isDebug;
    private string $projectDir;

    /** @psalm-suppress InvalidPropertyAssignmentValue */
    public function __construct(private \Closure $app, private array $args, array $options)
    {
        $this->projectDir = $options['project_dir'];
        $this->environment = $_SERVER[$options['env_var_name']] ?? $_ENV[$options['env_var_name']];
        $this->isDebug = (bool) ($options['debug'] ?? $_SERVER[$options['debug_var_name']] ?? $_ENV[$options['debug_var_name']] ?? true);
    }

    public function createKernel(): KernelInterface
    {
        return ($this->app)(...$this->args);
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->isDebug;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->getEnvironment();
    }
}
