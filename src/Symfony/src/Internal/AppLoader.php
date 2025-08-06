<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Symfony\Component\Dotenv\Dotenv;

/**
 * @template T
 * @internal
 */
final readonly class AppLoader
{
    public array $options;

    /**
     * @param \Closure(mixed ...$args): T $app
     */
    public function __construct(private \Closure $app, array $options)
    {
        if (!isset($options['env_var_name'], $options['debug_var_name'], $options['project_dir'])) {
            throw new \RuntimeException('Specify the env_var_name, env_var_name and project_dir options');
        }

        $this->options = $options;
    }

    /**
     * @return T
     */
    public function loadApp(): mixed
    {
        return ($this->app)(...\array_map($this->resolveArgument(...), (new \ReflectionFunction($this->app))->getParameters()));
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

        if (!($this->options['disable_dotenv'] ?? false) && \class_exists(Dotenv::class)) {
            $overrideExistingVars = (bool) ($this->options['dotenv_overload'] ?? false);
            $dotenv = (new Dotenv($envKey, $debugKey))->setProdEnvs((array) ($this->options['prod_envs'] ?? ['prod']));
            $dotenv->bootEnv(
                path: $this->options['project_dir'] . '/' . ($this->options['dotenv_path'] ?? '.env'),
                defaultEnv: 'dev',
                testEnvs: (array) ($this->options['test_envs'] ?? ['test']),
                overrideExistingVars: $overrideExistingVars,
            );

            $extraPaths = (array) ($this->options['dotenv_extra_paths'] ?? []);
            if ($extraPaths !== []) {
                $extraPaths = \array_map(fn(string $path) => $this->options['project_dir'] . '/' . $path, $this->options['dotenv_extra_paths']);
                $overrideExistingVars ? $dotenv->overload(...$extraPaths) : $dotenv->load(...$extraPaths);
            }
        } else {
            $_SERVER[$envKey] ??= $_ENV[$envKey] ?? 'dev';
            $_SERVER[$debugKey] ??= $_ENV[$debugKey] ?? !\in_array($_SERVER[$envKey], (array) ($this->options['prod_envs'] ?? ['prod']), true);
        }

        $debug = \filter_var($this->options['debug'] ?? $_SERVER[$debugKey] ?? $_ENV[$debugKey] ?? true, \FILTER_VALIDATE_BOOL);
        if ($debug) {
            \umask(0000);
            $_SERVER[$debugKey] = $_ENV[$debugKey] = '1';
        } else {
            $_SERVER[$debugKey] = $_ENV[$debugKey] = '0';
        }

        $_SERVER['PHPSS_PID_FILE']  = $this->getPidFile();
        $_SERVER['PHPSS_SOCKET_FILE']  = $this->getSocketFile();
    }

    private function resolveArgument(\ReflectionParameter $parameter): mixed
    {
        /** @psalm-suppress UndefinedMethod */
        $type = $parameter->getType()?->getName();

        if ($type === 'array' && $parameter->name === 'context') {
            return $_SERVER;
        }

        throw new \InvalidArgumentException(\sprintf(
            'Cannot resolve argument "%s $%s" in "%s" on line "%d"',
            $type ?? 'mixed',
            $parameter->name,
            $parameter->getDeclaringFunction()->getFileName(),
            $parameter->getDeclaringFunction()->getStartLine(),
        ));
    }

    /** @psalm-suppress InvalidReturnStatement, InvalidReturnType */
    public function getEnvironment(): string
    {
        return $_SERVER[$this->options['env_var_name']]
            ?? throw new \RuntimeException(\sprintf('The environment has not been set yet. Run %s::loadEnv() to set the environment', self::class));
    }

    public function getProjectDir(): string
    {
        return $this->options['project_dir'];
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->getEnvironment();
    }

    public function getPidFile(): string
    {
        return $this->options['pid_file'] ?? $this->getProjectDir() . '/var/run/phpss.pid';
    }

    public function getSocketFile(): string
    {
        return $this->options['socket_file'] ?? $this->getProjectDir() . '/var/run/phpss.socket';
    }

    public function getServerConfigFile(): string
    {
        return $this->options['config_file'] ?? $this->getProjectDir() . '/config/phpss.config.php';
    }
}
