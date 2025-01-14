<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware\CompressionMiddleware;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\HttpServer\HttpServerPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @internal
 */
final readonly class Runner implements RunnerInterface
{
    public function __construct(private AppLoader $appLoader)
    {
    }

    public function run(): int
    {
        $options = $this->appLoader->options;

        $server = new Server(
            pidFile: $options['pid_file'] ?? $this->appLoader->getProjectDir() . '/var/run/phpss.pid',
            socketFile: $options['socket_file'] ?? $this->appLoader->getProjectDir() . '/var/run/phpss.socket',
            stopTimeout: isset($options['stop_timeout']) ? (int) $options['stop_timeout'] : null,
            restartDelay: isset($options['restart_delay']) ? (int) $options['restart_delay'] : null,
        );

        $server->addPlugin(new HttpServerPlugin(
            http2Enable: (bool) ($options['http2_enable'] ?? true),
            httpConnectionTimeout: (int) ($options['http_connection_timeout'] ?? HttpDriver::DEFAULT_CONNECTION_TIMEOUT),
            httpHeaderSizeLimit: (int) ($options['http_header_size_limit'] ?? HttpDriver::DEFAULT_HEADER_SIZE_LIMIT),
            httpBodySizeLimit: (int) ($options['http_body_size_limit'] ?? UploadedFile::getMaxFilesize()),
            gzipMinLength: (int) ($options['gzip_min_length'] ?? CompressionMiddleware::DEFAULT_MINIMUM_LENGTH),
            gzipTypesRegex: (string) ($options['gzip_types_regex'] ?? CompressionMiddleware::DEFAULT_CONTENT_TYPE_REGEX),
        ));

        $server->addPlugin(new SymfonyPlugin(
            appLoader: $this->appLoader,
        ));

        $configFile = $options['config_file'] ?? ($this->appLoader->getProjectDir() . '/phpss.config.php');

        if (!\is_file($configFile)) {
            throw new \LogicException(\sprintf('Config file "%s" is missing', $configFile));
        }

        $configfurator = include $configFile;

        if (!$configfurator instanceof \Closure) {
            throw new \TypeError(sprintf('Invalid return value: "Closure" object expected, "%s" returned from "%s"', \get_debug_type($configfurator), $configFile));
        }

        $configfurator($server);

        return $server->run();
    }
}
