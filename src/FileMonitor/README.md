<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_file_monitor_light.svg">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_file_monitor_dark.svg">
  </picture>
</p>

## File Monitor Plugin for PHPStreamServer
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)
![Version](https://img.shields.io/github/v/tag/phpstreamserver/file-monitor?label=Version&filter=v*.*.*&sort=semver&color=374151)
![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/file-monitor?label=Downloads&color=f28d1a)

The File Monitor Plugin for **PHPStreamServer** extends the core functionality by automatically monitoring file changes within specified directories.
When changes are detected, the plugin triggers a workers reload. In always-in-memory architectures, the server must to be reloaded to take effect after file changes.  
Useful for development environments.

### Features
 - Watch specific files in specific directories.
 - Uses inotify signals from the operating system.

### Install
```bash
$ composer require phpstreamserver/core phpstreamserver/file-monitor
```

### Configure
Here is an example of a simple server configuration.  
Each time the files in the directory change, the server is reloaded.

```php
// server.php

use PHPStreamServer\Core\Server;
use PHPStreamServer\Core\Worker\WorkerProcess;
use PHPStreamServer\Plugin\FileMonitor\FileMonitorPlugin;
use PHPStreamServer\Plugin\FileMonitor\WatchDir;

$server = new Server();

$server->addPlugin(
    new FileMonitorPlugin(
        new WatchDir(sourceDir: __DIR__, filePattern: ['*'], invalidateOpcache: true)
    ),
);

$server->addWorker(
    new WorkerProcess(
        name: 'Supervised Program',
        count: 1,
        onStart: function (WorkerProcess $worker): void {
            // custom long running process
        },
    ),
);

exit($server->run());
```

### Run
```bash
$ php server.php start
```
