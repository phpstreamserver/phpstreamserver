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
 *  A runtime for running applications in PHPStreamServer.
 *
 *  It supports the following options:
 *   - "env_var_name" and "debug_var_name": define the names of the environment variables that hold the Symfony environment and the debug flag, respectively;
 *   - "env": defines the name of the environment the application runs in;
 *   - "debug": toggles debug mode and defaults to the "APP_DEBUG" environment variable;
 *   - "disable_dotenv": disables looking for .env files;
 *   - "dotenv_path": defines the path of the .env file - defaults to ".env";
 *   - "prod_envs": defines the names of the production environments - defaults to ["prod"];
 *   - "test_envs": defines the names of the test environments - defaults to ["test"];
 *   - "dotenv_overload": tells Dotenv to override existing variables;
 *   - "dotenv_extra_paths": defines a list of additional .env files;
 *
 *  PHPStreamServer-specific options:
 *   - "config_file": path to the PHPStreamServer configuration file - defaults to "config/phpss.config.php";
 *   - "pid_file": path to the PID file - defaults to "var/run/phpss.pid";
 *   - "socket_file": path to the Unix socket file - defaults to "var/run/phpss.socket";
 *   - "stop_timeout": time to wait (in seconds) before forcefully terminating workers during shutdown - defaults to "10";
 *   - "restart_delay": delay (in seconds) between worker restarts - defaults to "0.25";
 *   - "http2_enabled": enables support for the HTTP/2 protocol - defaults to "true";
 *   - "http_connection_timeout": timeout duration (in seconds) for idle HTTP connections - defaults to "60";
 *   - "http_header_size_limit": maximum allowed size for HTTP headers - defaults to "32768";
 *   - "http_body_size_limit": maximum allowed size for the HTTP request body - defaults to the smaller of the "post_max_size" and "upload_max_filesize" PHP INI values;
 *   - "gzip_min_length": minimum response size required to enable gzip compression - defaults to "860";
 *   - "gzip_types_regex": regular expression to match content types that will be gzipped;
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
            return new ServerRunner(new AppLoader($application->app, $this->options));
        }

        if ($application instanceof ConsoleApplication) {
            return new ConsoleRunner(new AppLoader($application->app, $this->options));
        }

        throw new \LogicException(\sprintf('"%s" does not know how to handle apps of type "%s"', \get_debug_type($this), \get_debug_type($application)));
    }

    public function getResolver(callable $callable, \ReflectionFunction|null $reflector = null): ResolverInterface
    {
        $closure = match (true) {
            $callable instanceof ServerApplication, $callable instanceof ConsoleApplication => $callable(...),
            default => static fn(): ConsoleApplication => new ConsoleApplication($callable(...)),
        };

        return new ClosureResolver($closure, static fn(): array => []);
    }
}
