<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use Symfony\Component\HttpKernel\KernelInterface;

final readonly class KernelFactory
{
    public string $projectDir;
    public string $environment;
    public bool $isDebug;

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
}
