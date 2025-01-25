<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\GelfTransport;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;

/**
 * @internal
 */
final class GelfHttpTransport implements GelfTransport
{
    private HttpClient $httpClient;
    private bool $inErrorState = false;

    public function __construct(private readonly string $url)
    {
        if (!\class_exists(HttpClient::class)) {
            throw new \RuntimeException(\sprintf('You cannot use "%s" as the "http-client" package is not installed. Try running "composer require amphp/http-client".', __CLASS__));
        }
    }

    public function start(): void
    {
        $this->httpClient = (new HttpClientBuilder())->followRedirects(0)->build();
    }

    public function write(string $buffer): void
    {
        $request = new Request($this->url, 'POST', $buffer);
        $request->setHeader('Content-Type', 'application/json');
        $request->setTransferTimeout(5);

        try {
            $this->httpClient->request($request);
            $this->inErrorState = false;
        } catch (SocketException $e) {
            if ($this->inErrorState === false) {
                \trigger_error($e->getMessage(), E_USER_WARNING);
                $this->inErrorState = true;
            }
        }
    }
}
