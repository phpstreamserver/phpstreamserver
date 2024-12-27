<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_core_light.svg">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_core_dark.svg">
  </picture>
</p>

## PHPStreamServer - PHP Application Server
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)
![Version](https://img.shields.io/github/v/tag/phpstreamserver/phpstreamserver?label=Version&filter=v*.*.*&sort=semver&color=374151)
![Tests Status](https://img.shields.io/github/actions/workflow/status/phpstreamserver/phpstreamserver/tests.yaml?label=Tests&branch=main)

⚠️ This is the **monorepo** for the main components of [PHPStreamServer](https://phpstreamserver.dev/) ⚠️

**PHPStreamServer** is a high performance event-loop based application server and supervisor for PHP written in PHP.  
PHPStreamServer ships with a number of plugins to extend functionality such as http server, scheduler and logger. See all the plugin packages below.  
With all the power of plugins, it can replace traditional setup for running php applications like nginx, php-fpm, cron and supervisor.

### Documentation
Please read the official documentation: https://phpstreamserver.dev/

### Packages
| Package                                                         | Description                                                                                       |
|-----------------------------------------------------------------|---------------------------------------------------------------------------------------------------|
| [**Core**](https://github.com/phpstreamserver/core)             | The core of PHPStreamServer with a built-in supervisor.                                           |
| [Http Server](https://github.com/phpstreamserver/http-server)   | Plugin that implements an asynchronous HTTP server.                                               |
| [Scheduler](https://github.com/phpstreamserver/scheduler)       | Plugin for scheduling tasks. Works similar to cron.                                               |
| [Logger](https://github.com/phpstreamserver/logger)             | Plugin that implements a powerful PSR-compatible logger that can be used by workers.              |
| [File Monitor](https://github.com/phpstreamserver/file-monitor) | Plugin to monitor files and reload server when files are changed. Useful for development.         |
| [Metrics](https://github.com/phpstreamserver/metrics)           | Plugin that exposes an endpoint with Prometheus metrics. Custom metrics can be sent from workers. |
| [Symfony](https://github.com/phpstreamserver/symfony)           | Symfony bundle to integrate PHPStreamServer with a symfony application.                           |
