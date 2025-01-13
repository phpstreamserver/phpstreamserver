<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_logger_light.svg">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_logger_dark.svg">
  </picture>
</p>

## Logger Plugin for PHPStreamServer
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)
![Version](https://img.shields.io/github/v/tag/phpstreamserver/logger?label=Version&filter=v*.*.*&sort=semver&color=374151)
![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/logger?label=Downloads&color=f28d1a)

The Logger Plugin for **PHPStreamServer** extends the core functionality by providing a configurable PSR-compatible logger.
It allows to capture and route logs from different channels and severities to various destinations, such as files, stdout,
Graylog servers (via the GELF protocol), and syslog.

### Features
 - Route logs by channel and severity to different destinations.
 - Rotate file logs.
 - Compress file logs.
 - Customizable format: Supports JSON, human-readable and custom formats.
 - Customizable handlers: Implement your own custom log handlers.

### Install
```bash
$ composer require phpstreamserver/core phpstreamserver/logger
```

### Configure
Here is an example of a simple server configuration with logger.

```php
// server.php

use PHPStreamServer\Core\Server;
use PHPStreamServer\Core\Worker\WorkerProcess;
use PHPStreamServer\Plugin\Logger\Handler\ConsoleHandler;
use PHPStreamServer\Plugin\Logger\Handler\FileHandler;
use PHPStreamServer\Plugin\Logger\LoggerInterface;
use PHPStreamServer\Plugin\Logger\LoggerPlugin;

$server = new Server();

$server->addPlugin(
    new LoggerPlugin(
        new ConsoleHandler(),
        new FileHandler(
            filename: __DIR__ . '/log.log',
            rotate: true,
        ),
    ),
);

$server->addWorker(
    new WorkerProcess(
        name: 'Supervised Program',
        count: 1,
        onStart: function (WorkerProcess $worker): void {
            $logger = $worker->container->getService(LoggerInterface::class);
            $logger->info('test message');
        },
    ),
);

exit($server->run());
```

### Run
```bash
$ php server.php start
```
