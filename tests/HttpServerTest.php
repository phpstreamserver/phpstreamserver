<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use Amp\Http\Client\Request;
use PHPStreamServer\Test\data\PHPSSTestCase;

final class HttpServerTest extends PHPSSTestCase
{
    public function testHttpServerIsAvailableOnHttpPort(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('http://127.0.0.1:9080'));

        // Assert
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Hello world', $response->getBody()->buffer());
    }

    public function testHttpServerIsAvailableOnHttpsPort(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('https://127.0.0.1:9081'));

        // Assert
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Hello world', $response->getBody()->buffer());
    }

    public function testLargeHttp2Response(): void
    {
        $curl = \curl_init('https://127.0.0.1:9081/large');

        \curl_setopt_array($curl, [
            \CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_2TLS,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $body = \curl_exec($curl);

        $this->assertNotFalse($body, \curl_error($curl));
        $this->assertSame(\str_repeat('x', 3 * 16384), $body);
    }

    public function testInternalServerErrorIsReturned(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('https://127.0.0.1:9081/error'));

        // Assert
        $this->assertSame(500, $response->getStatus());
    }

    public function testNotFoundIsReturned(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('https://127.0.0.1:9081/qwertyasdf'));

        // Assert
        $this->assertSame(404, $response->getStatus());
    }
}
