<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final readonly class KernelLoader
{
    public string $projectDir;
    public string $environment;
    public bool $isDebug;

    /** @psalm-suppress InvalidPropertyAssignmentValue */
    public function __construct(private \Closure $app, private array $args, public array $options)
    {
        $this->projectDir = \rtrim($options['project_dir'], '/');
        $this->environment = $_SERVER[$options['env_var_name']] ?? $_ENV[$options['env_var_name']];
        $this->isDebug = (bool) ($options['debug'] ?? $_SERVER[$options['debug_var_name']] ?? $_ENV[$options['debug_var_name']] ?? true);
    }

    public function createKernel(): KernelInterface
    {
        return ($this->app)(...$this->args);
    }
}
