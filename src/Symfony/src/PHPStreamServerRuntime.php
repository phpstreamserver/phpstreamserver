<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use PHPStreamServer\Symfony\Internal\AppLoader;
use PHPStreamServer\Symfony\Internal\ConsoleRunner;
use PHPStreamServer\Symfony\Internal\ServerRunner;
use Symfony\Component\Runtime\Resolver\ClosureResolver;
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
 *   - "config_file" Path to the phpss config file - defaults to "config/phpss.config.php";
 *   - "pid_file" Path to the pid file - defaults to "var/run/phpss.pid";
 *   - "socket_file" Path to the Unix socket file - defaults to "var/run/phpss.socket";
 *   - "stop_timeout" Maximum time to wait before forcefully terminating workers during shutdown - defaults to "10";
 *   - "restart_delay" Delay between worker restarts - defaults to "0.25";
 *   - "http2_enable" Enables support for HTTP/2 protocol - defaults to "true";
 *   - "http_connection_timeout" Timeout duration for idle HTTP connections - defaults to "60";
 *   - "http_header_size_limit" Maximum allowed size for HTTP headers - defaults to "32768";
 *   - "http_body_size_limit" Maximum allowed size for the HTTP request body - defaults to min of ini options "post_max_size" and "upload_max_filesize";
 *   - "gzip_min_length" Minimum response size required to enable gzip compression - defaults to "860";
 *   - "gzip_types_regex" Regular expression to match content types eligible for gzip compression;
 */
final class PHPStreamServerRuntime implements RuntimeInterface
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
        if ($application instanceof ServerApplication) {
            return new ServerRunner(new AppLoader($application->kernelFactory, $this->options));
        }

        if ($application instanceof ConsoleApplication) {
            return new ConsoleRunner(new AppLoader($application->kernelFactory, $this->options));
        }

        throw new \LogicException(\sprintf('"%s" doesn\'t know how to handle apps of type "%s"', \get_debug_type($this), \get_debug_type($application)));
    }

    public function getResolver(callable $callable, \ReflectionFunction|null $reflector = null): ResolverInterface
    {
        return new ClosureResolver($callable(...), static fn () => []);
    }
}
