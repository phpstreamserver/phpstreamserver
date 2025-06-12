<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware\CompressionMiddleware;
use PHPStreamServer\Core\Console\Options;
use PHPStreamServer\Core\Internal\Console\OptionDefinition;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\HttpServer\HttpServerPlugin;
use PHPStreamServer\Plugin\Scheduler\SchedulerPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @internal
 */
final readonly class ServerRunner implements RunnerInterface
{
    private Server $server;

    public function __construct(private AppLoader $appLoader)
    {
    }

    public function run(): int
    {
        \set_time_limit(0);

        $inputOptions = new Options(
            argv: $_SERVER['argv'] ?? [],
            defaultOptionDefinitions: [new OptionDefinition('env', 'e'), new OptionDefinition('no-debug')],
        );

        $env = $inputOptions->getOption('env');
        $noDebug = $inputOptions->getOption('no-debug');
        unset($inputOptions);

        if (\is_string($env) && $env !== '') {
            $envVarName = $this->appLoader->options['env_var_name'];
            \putenv($envVarName . '=' . $_SERVER[$envVarName] = $_ENV[$envVarName] = $env);
        }

        if ($noDebug === true) {
            $debugVarName = $this->appLoader->options['debug_var_name'];
            \putenv($debugVarName . '=' . $_SERVER[$debugVarName] = $_ENV[$debugVarName] = '0');
        }

        $this->appLoader->loadEnv();
        $options = $this->appLoader->options;

        $this->server = new Server(
            pidFile: $options['pid_file'] ?? $this->appLoader->getProjectDir() . '/var/run/phpss.pid',
            socketFile: $options['socket_file'] ?? $this->appLoader->getProjectDir() . '/var/run/phpss.socket',
            stopTimeout: isset($options['stop_timeout']) ? (int) $options['stop_timeout'] : null,
            restartDelay: isset($options['restart_delay']) ? (int) $options['restart_delay'] : null,
        );

        $this->server->addPlugin(new HttpServerPlugin(
            http2Enable: (bool) ($options['http2_enable'] ?? true),
            httpConnectionTimeout: (int) ($options['http_connection_timeout'] ?? HttpDriver::DEFAULT_CONNECTION_TIMEOUT),
            httpHeaderSizeLimit: (int) ($options['http_header_size_limit'] ?? HttpDriver::DEFAULT_HEADER_SIZE_LIMIT),
            httpBodySizeLimit: (int) ($options['http_body_size_limit'] ?? UploadedFile::getMaxFilesize()),
            gzipMinLength: (int) ($options['gzip_min_length'] ?? CompressionMiddleware::DEFAULT_MINIMUM_LENGTH),
            gzipTypesRegex: (string) ($options['gzip_types_regex'] ?? CompressionMiddleware::DEFAULT_CONTENT_TYPE_REGEX),
        ));

        $this->server->addPlugin(new SymfonyPlugin(
            appLoader: $this->appLoader,
        ));

        if (\class_exists(SchedulerPlugin::class)) {
            $this->server->addPlugin(new SchedulerPlugin());
        }

        $configFile = $options['config_file'] ?? $this->appLoader->getProjectDir() . '/config/phpss.config.php';

        if (!\is_file($configFile)) {
            throw new \LogicException(\sprintf('Config file "%s" is missing', $configFile));
        }

        $configfurator = include $configFile;

        if (!$configfurator instanceof \Closure) {
            throw new \TypeError(\sprintf('Invalid return value: "Closure" object expected, "%s" returned from "%s"', \get_debug_type($configfurator), $configFile));
        }

        $configfurator(...\array_map($this->resolveConfigArgument(...), (new \ReflectionFunction($configfurator))->getParameters()));

        return $this->server->run();
    }

    private function resolveConfigArgument(\ReflectionParameter $parameter): mixed
    {
        /** @psalm-suppress UndefinedMethod */
        $type = $parameter->getType()?->getName();

        if ($type === Server::class && $parameter->name === 'server') {
            return $this->server;
        } elseif ($type === 'array' && $parameter->name === 'context') {
            return $_SERVER;
        } elseif ($type === 'string' && $parameter->name === 'projectDir') {
            return $this->appLoader->options['project_dir'];
        } elseif ($type === 'string' && $parameter->name === 'env') {
            /** @psalm-suppress PossiblyInvalidCast */
            return (string) $_SERVER[$this->appLoader->options['env_var_name']];
        } elseif ($type === 'bool' && $parameter->name === 'debug') {
            return $_SERVER[$this->appLoader->options['debug_var_name']] === '1';
        }

        throw new \InvalidArgumentException(\sprintf(
            'Cannot resolve argument "%s $%s" in "%s" on line "%d"',
            $type ?? 'mixed',
            $parameter->name,
            $parameter->getDeclaringFunction()->getFileName(),
            $parameter->getDeclaringFunction()->getStartLine(),
        ));
    }
}
