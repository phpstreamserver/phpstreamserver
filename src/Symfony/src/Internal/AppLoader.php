<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final readonly class AppLoader
{
    public array $options;
    public string $projectDir;

    /** @psalm-suppress InvalidPropertyAssignmentValue */
    public function __construct(private \Closure $app, array $options)
    {
        $this->options = $options;
        $this->projectDir = $this->options['project_dir'];
    }

    public function createKernel(): KernelInterface
    {
        return ($this->app)(...\array_map($this->resolveArgument(...), (new \ReflectionFunction($this->app))->getParameters()));
    }

    private function resolveArgument(\ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType()?->getName();

        if ($type === 'array' && $parameter->name === 'context') {
            return $_SERVER;
        }

        throw new \InvalidArgumentException(\sprintf(
            'Cannot resolve argument "%s $%s" in "%s" on line "%d"',
            $type,
            $parameter->name,
            $parameter->getDeclaringFunction()->getFileName(),
            $parameter->getDeclaringFunction()->getStartLine(),
        ));
    }

    public function loadEnv(): void
    {
        static $server;
        static $env;
        $server ??= $_SERVER;
        $env ??= $_ENV;
        $_SERVER = $server;
        $_ENV = $env;

        (new Dotenv($this->options['env_var_name'] ?? 'APP_ENV', $this->options['debug_var_name'] ?? 'APP_DEBUG'))
            ->setProdEnvs(
                prodEnvs: (array) ($this->options['prod_envs'] ?? ['prod']),
            )->bootEnv(
                path: $this->options['project_dir'].'/'.($this->options['dotenv_path'] ?? '.env'),
                testEnvs: (array) ($this->options['test_envs'] ?? ['test']),
            );
    }
}
