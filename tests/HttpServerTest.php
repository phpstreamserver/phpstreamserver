<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use Amp\Http\Client\Request;
use PHPStreamServer\Test\data\PHPSSTestCase;

final class HttpServerTest extends PHPSSTestCase
{
    public function testWebserverIsAvailableOnHttpPort(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('http://127.0.0.1:9080'));

        // Assert
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Hello world', $response->getBody()->buffer());
    }

    public function testWebserverIsAvailableOnHttpsPort(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('https://127.0.0.1:9081'));

        // Assert
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Hello world', $response->getBody()->buffer());
    }

    public function testInternalServerErrorPage(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('https://127.0.0.1:9081/error'));

        // Assert
        $this->assertSame(500, $response->getStatus());
    }

    public function testNotFoundPage(): void
    {
        // Arrange
        $client = $this->createHttpClient();

        // Act
        $response = $client->request(new Request('https://127.0.0.1:9081/qwertyasdf'));

        // Assert
        $this->assertSame(404, $response->getStatus());
    }
}
