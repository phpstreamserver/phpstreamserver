<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Internal;

use Amp\Http\HttpStatus;
use PHPStreamServer\Core\Server;

/**
 * @internal
 */
final readonly class NotFoundPage
{
    public int $status;
    public string $reason;
    public string $server;

    public function __construct(int $status = HttpStatus::NOT_FOUND)
    {
        $this->status = $status;
        $this->reason = HttpStatus::getReason($status);
        $this->server = Server::getProductName();
    }

    public function toHtml(): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <title>{$this->status} {$this->reason}</title>
                <style>
                    body {color: #333; font-family: Verdana, sans-serif; font-size: 0.9em; margin: 0; padding: 1rem;}
                    h1 {margin: 0;}
                    hr {margin: 1rem 0; border: 0; border-bottom: 1px solid #333;}
                </style>
            </head>
            <body>
            <h1>{$this->status} {$this->reason}</h1>
            <div style="margin: 0.5rem auto;">Prometheus metrics link: <a href="/metrics">/metrics</a></div>
            <hr>
            <div>{$this->server}</div>
            </body>
            </html>
            HTML;
    }
}
