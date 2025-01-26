<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_symfony_light.svg">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_symfony_dark.svg">
  </picture>
</p>

## Symfony Bundle for PHPStreamServer
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)
![Version](https://img.shields.io/github/v/tag/phpstreamserver/symfony?label=Version&filter=v*.*.*&sort=semver&color=374151)
![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/symfony?label=Downloads&color=f28d1a)

This bundle provides a PHPStreamServer integration with the Symfony framework to run your application in a highly efficient event-loop based runtime.

## Getting started
### Install composer packages
```bash
$ composer require phpstreamserver/symfony
```

### Enable the bundle
```php
<?php
// config/bundles.php

return [
    // ...
    PHPStreamServer\Symfony\PHPStreamServerBundle::class => ['all' => true],
];
```

### Create phpss.config.php in the root directory
```php
# phpss.config.php

use PHPStreamServer\Core\ReloadStrategy\ExceptionReloadStrategy;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Symfony\Worker\SymfonyHttpServerProcess;

return static function (Server $server): void {
    $server->addWorker(new SymfonyHttpServerProcess(
        listen: '0.0.0.0:80',
        count: 1,
        reloadStrategies: [
            new ExceptionReloadStrategy(),
        ],
    ));
};
```

### Create phpss file in bin directory
```php
# bin/phpss

use App\Kernel;
use PHPStreamServer\Symfony\Runtime;

$_SERVER['APP_RUNTIME'] = Runtime::class;

require_once \dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
```

### Start the server
```bash
$ bin/phpss start
```
