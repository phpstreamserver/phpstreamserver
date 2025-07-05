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
### Install
```bash
$ composer require phpstreamserver/symfony
```

### Enable the bundle
```php
// config/bundles.php

return [
    // ...
    PHPStreamServer\Symfony\PHPStreamServerBundle::class => ['all' => true],
];
```

### Set PHPStreamServerRuntime as the application runtime
Use the `APP_RUNTIME` environment variable or by specifying the `extra.runtime.class` in `composer.json` to change the Runtime class to `PHPStreamServer\Symfony\PHPStreamServerRuntime`.
```json
{
  "require": {
    "...": "..."
  },
  "extra": {
    "runtime": {
      "class": "PHPStreamServer\\Symfony\\PHPStreamServerRuntime"
    }
  }
}
```

### Create config/phpss.config.php file
```php
<?php

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

### Create bin/phpss file

```php
#!/usr/bin/env php
<?php

use App\Kernel;
use PHPStreamServer\Symfony\ServerApplication;

require_once \dirname(__DIR__) . '/vendor/autoload_runtime.php';

return new ServerApplication(static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
});
```

### Create bin/console file

```php
#!/usr/bin/env php
<?php

use App\Kernel;
use PHPStreamServer\Symfony\ConsoleApplication;

require_once \dirname(__DIR__) . '/vendor/autoload_runtime.php';

return new ConsoleApplication(static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
});
```

\* Modifying the `bin/console` file is essential to integrate console commands with PHPStreamServer—do not skip this step.

### Start the server
```bash
$ bin/phpss start
```
