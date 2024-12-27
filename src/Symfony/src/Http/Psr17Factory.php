<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Http;

use Luzrain\PHPStreamServer\Server\Http\Psr7\ResourceStream;
use Luzrain\PHPStreamServer\Server\Http\Psr7\Response;
use Luzrain\PHPStreamServer\Server\Http\Psr7\StringStream;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

final class Psr17Factory implements ResponseFactoryInterface, ServerRequestFactoryInterface, StreamFactoryInterface, UploadedFileFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, null, [], '1.1', $reasonPhrase);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return new StringStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new ResourceStream(\fopen($filename, $mode));
    }

    /**
     * @param resource $resource
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new ResourceStream($resource);
    }

    /**
     * @param UriInterface|string $uri
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        throw new \LogicException(\sprintf('%s::createServerRequest() not implemented', self::class));
    }

    public function createUploadedFile(StreamInterface $stream, int $size = null, int $error = \UPLOAD_ERR_OK, string $clientFilename = null, string $clientMediaType = null): UploadedFileInterface
    {
        throw new \LogicException(\sprintf('%s::createUploadedFile() not implemented', self::class));
    }
}
