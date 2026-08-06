<p align="center">
  <a href="https://phpstreamserver.dev/">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_core_light.svg">
      <img alt="PHPStreamServer" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_core_dark.svg">
    </picture>
  </a>
</p>

## Application server and process manager for modern PHP applications

![PHP 8.2 or later](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb3.svg)
[![Latest release](https://img.shields.io/github/v/tag/phpstreamserver/phpstreamserver?label=Version&filter=v*.*.*&sort=semver&color=374151)](https://github.com/phpstreamserver/phpstreamserver/releases)
[![Test status](https://img.shields.io/github/actions/workflow/status/phpstreamserver/phpstreamserver/tests.yaml?label=Tests&branch=main)](https://github.com/phpstreamserver/phpstreamserver/actions/workflows/tests.yaml)
[![MIT license](https://img.shields.io/badge/License-MIT-374151.svg)](LICENSE)

**PHPStreamServer** is an event-loop-based application server and process supervisor built entirely in PHP.

Its extensible plugin system provides HTTP application serving, task scheduling, and process supervision within a unified runtime, without requiring Nginx, PHP-FPM, Cron, or Supervisor.

Powered by the [Revolt](https://revolt.run/) event loop and the [AMPHP](https://amphp.org/) ecosystem, it enables asynchronous, concurrent execution in PHP applications.

## Features

- Asynchronous HTTP server
- Worker lifecycle management
- External process supervision
- Cron-like task scheduling
- Configurable log routing
- Prometheus-compatible metrics
- Automatic reloads on file changes

## Getting Started

Follow the [Quick Start](https://phpstreamserver.dev/docs/general/quick-start) to configure and run your first application. Install only the [plugins](https://phpstreamserver.dev/docs/plugins/) your application needs.

## Resources

- [Documentation](https://phpstreamserver.dev/docs/general/)
- [Symfony integration](https://phpstreamserver.dev/docs/integrations/symfony)
- [Issue tracker](https://github.com/phpstreamserver/phpstreamserver/issues)

## License

PHPStreamServer is released under the [MIT License](LICENSE).
