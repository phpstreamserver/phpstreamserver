<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_http_server_light.svg">
    <img alt="PHPStreamServer logo" align="center" width="70%" src="https://raw.githubusercontent.com/phpstreamserver/.github/refs/heads/main/assets/phpss_http_server_dark.svg">
  </picture>
</p>

## HTTP Server Plugin for PHPStreamServer
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)
![Version](https://img.shields.io/github/v/tag/phpstreamserver/http-server?label=Version&filter=v*.*.*&sort=semver&color=374151)
![Downloads](https://img.shields.io/packagist/dt/phpstreamserver/http-server?label=Downloads&color=f28d1a)

The HTTP Server Plugin for **PHPStreamServer** extends the core functionality by providing a high performance, asynchronous HTTP server.
It works in the event loop and always persists in memory, enabling fast request handling and reducing startup overhead.  

### Features:
 - Non-blocking, high concurrency request handling.
 - Protocol support: HTTP/1.1, HTTP/2.
 - HTTPS encrypted connections.
 - GZIP compression.
 - Serve static files: Can serve files from a directory, making it easy to host static assets like HTML, CSS, JavaScript, and images.
 - Middleware support: Integrates middleware for flexible request/response processing.

### Install
```bash
$ composer require phpstreamserver/core phpstreamserver/http-server
```

### Configure
Here is an example of a simple HTTP server configuration.

```php
// server.php

use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\HttpServer\HttpServerPlugin;
use PHPStreamServer\Plugin\HttpServer\Worker\HttpServerProcess;

$server = new Server();

$server->addPlugin(
    new HttpServerPlugin(),
);

$server->addWorker(
    new HttpServerProcess(
        name: 'Web Server',
        count: 4,
        listen: '0.0.0.0:8080',
        onStart: function (HttpServerProcess $worker): void {
            // initialization
        },
        onRequest: function (Request $request, HttpServerProcess $worker): Response {
            return match ($request->getUri()->getPath()) {
                '/' => new Response(body: 'Hello world'),
                '/ping' => new Response(body: 'pong'),
                default => throw new HttpErrorException(404),
            };
        }
    ),
);

exit($server->run());
```

### Run
```bash
$ php server.php start
```
