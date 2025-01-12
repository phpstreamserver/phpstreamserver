<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use PHPStreamServer\Symfony\Internal\AppLoader;
use PHPStreamServer\Symfony\Internal\Runner;
use Symfony\Component\Runtime\ResolverInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\RuntimeInterface;

/**
 *  A runtime to run application in PHPStreamServer.
 *
 *  It supports the following options:
 *   - "env_var_name" and "debug_var_name" define the name of the env vars that hold the Symfony env and the debug flag respectively;
 *   - "env" to define the name of the environment the app runs in;
 *   - "debug" toggles displaying errors and defaults to the "APP_DEBUG" environment variable;
 *   - "disable_dotenv" to disable looking for .env files;
 *   - "dotenv_path" to define the path of dot-env files - defaults to ".env";
 *   - "prod_envs" to define the names of the production envs - defaults to ["prod"];
 *   - "test_envs" to define the names of the test envs - defaults to ["test"];
 *
 *  PHPStreamServer specific options:
 *   - "config_file" Path to the phpss config file - defaults to "phpss.config.php";
 *   - "pid_file" Path to the pid file;
 *   - "socket_file" Path to the Unix socket file;
 *   - "stop_timeout" Maximum time to wait before forcefully terminating workers during shutdown;
 *   - "restart_delay" Delay between worker restarts;
 *   - "http2_enable" Enables support for HTTP/2 protocol;
 *   - "http_connection_timeout" Timeout duration for idle HTTP connections;
 *   - "http_header_size_limit" Maximum allowed size for HTTP headers;
 *   - "http_body_size_limit" Maximum allowed size for the HTTP request body;
 *   - "gzip_min_length" Minimum response size required to enable gzip compression;
 *   - "gzip_types_regex" Regular expression to match content types eligible for gzip compression;
 */
final class Runtime implements RuntimeInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $options['env_var_name'] ??= 'APP_ENV';
        $options['debug_var_name'] ??= 'APP_DEBUG';
        $this->options = $options;
    }

    public function getRunner(object|null $application): RunnerInterface
    {
        if (!$application instanceof AppLoader) {
            throw new \LogicException(\sprintf('Not supported application type, %s was expected', AppLoader::class));
        }

        return new Runner($application);
    }

    public function getResolver(callable $callable, \ReflectionFunction|null $reflector = null): ResolverInterface
    {
        $callable = $callable(...);

        return new class ($callable, $this->options) implements ResolverInterface {
            public function __construct(private readonly \Closure $callable, private readonly array $options)
            {
            }

            public function resolve(): array
            {
                return [static fn(mixed ...$args) => new AppLoader(...$args), [$this->callable, $this->options]];
            }
        };
    }
}
