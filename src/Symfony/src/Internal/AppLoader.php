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

        $envKey = $this->options['env_var_name'];
        $debugKey = $this->options['debug_var_name'];

        if (isset($this->options['env'])) {
            $_SERVER[$envKey] = $this->options['env'];
        }

        if (!($options['disable_dotenv'] ?? false) && \class_exists(Dotenv::class)) {
            (new Dotenv($envKey, $debugKey))
                ->setProdEnvs(
                    prodEnvs: (array) ($this->options['prod_envs'] ?? ['prod']),
                )->bootEnv(
                    path: $this->options['project_dir'].'/'.($this->options['dotenv_path'] ?? '.env'),
                    testEnvs: (array) ($this->options['test_envs'] ?? ['test']),
                );
        } else {
            $_SERVER[$envKey] ??= $_ENV[$envKey] ?? 'dev';
            $_SERVER[$debugKey] ??= $_ENV[$debugKey] ?? !\in_array($_SERVER[$envKey], (array) ($options['prod_envs'] ?? ['prod']), true);
        }

        $debug = $options['debug'] ?? $_SERVER[$debugKey] ?? $_ENV[$debugKey] ?? true;
        if (!\is_bool($debug)) {
            $debug = filter_var($debug, \FILTER_VALIDATE_BOOL);
        }

        if ($debug) {
            \umask(0000);
            $_SERVER[$debugKey] = $_ENV[$debugKey] = '1';
        } else {
            $_SERVER[$debugKey] = $_ENV[$debugKey] = '0';
        }
    }
}
