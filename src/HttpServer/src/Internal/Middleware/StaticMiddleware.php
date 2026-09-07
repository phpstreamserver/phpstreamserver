<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\Internal\Middleware;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use PHPStreamServer\Plugin\HttpServer\Internal\MimeTypeMapper;

/**
 * @internal
 */
final readonly class StaticMiddleware implements Middleware
{
    private const S_IFMT = 0170000;
    private const S_IFREG = 0100000;

    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = \rtrim(\realpath($dir) ?: $dir, '/');
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        if (null === $file = $this->findFileInPublicDirectory($request->getUri()->getPath())) {
            return $requestHandler->handleRequest($request);
        }

        if (false === $fd = \fopen($file, 'rn')) {
            return $requestHandler->handleRequest($request);
        }

        $stat = \fstat($fd);

        // Ensure the opened resource is a regular file and not a directory or pipe
        if ($stat === false || (($stat['mode'] & self::S_IFMT) !== self::S_IFREG)) {
            \fclose($fd);

            return $requestHandler->handleRequest($request);
        }

        $headers = [
            'Content-Type' => MimeTypeMapper::lookupMimeTypeFromPath($file),
            'Content-Length' => (string) $stat['size'],
        ];

        if ($request->getMethod() === 'HEAD') {
            \fclose($fd);

            return new Response(headers: $headers);
        }

        return new Response(body: new ReadableResourceStream($fd), headers: $headers);
    }

    private function findFileInPublicDirectory(string $requestPath): string|null
    {
        if ($requestPath === '/' || $requestPath === '' || \str_ends_with($requestPath, '/')) {
            return null;
        }

        $path = \realpath($this->dir . '/' . \ltrim($requestPath, '/'));

        if ($path === false || !\str_starts_with($path, $this->dir . '/')) {
            return null;
        }

        return $path;
    }
}
