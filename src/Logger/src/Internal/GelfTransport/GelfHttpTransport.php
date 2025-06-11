<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\GelfTransport;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;

/**
 * @internal
 */
final class GelfHttpTransport implements GelfTransport
{
    private const TIMEOUT = 5;

    private HttpClient $httpClient;
    private bool $inErrorState = false;

    public function __construct(private readonly string $url)
    {
        if (!\class_exists(HttpClient::class)) {
            throw new \RuntimeException(\sprintf('You cannot use "%s" as the "http-client" package is not installed. Try running "composer require amphp/http-client"', __CLASS__));
        }
    }

    public function start(): void
    {
        $this->httpClient = (new HttpClientBuilder())
            ->retry(0)
            ->followRedirects(0)
            ->skipAutomaticCompression()
            ->allowDeprecatedUriUserInfo()
            ->build();
    }

    public function write(string $buffer): void
    {
        $request = new Request($this->url, 'POST', $buffer);
        $request->setHeader('Content-Type', 'application/json');
        $request->setTcpConnectTimeout(self::TIMEOUT);
        $request->setTransferTimeout(self::TIMEOUT);

        try {
            $this->httpClient->request($request);
            $this->inErrorState = false;
        } catch (SocketException|TimeoutException $e) {
            if ($this->inErrorState === false) {
                \trigger_error($e->getMessage(), E_USER_WARNING);
                $this->inErrorState = true;
            }
        }
    }
}
