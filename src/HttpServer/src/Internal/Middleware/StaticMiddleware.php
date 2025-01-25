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
    public function __construct(
        private string $dir,
    ) {
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        if (null === $file = $this->findFileInPublicDirectory($request->getUri()->getPath())) {
            return $requestHandler->handleRequest($request);
        }

        $fd = \fopen($file, 'r');
        $size = \fstat($fd)['size'] ?? 0;
        $headers = [
            'Content-Type' => MimeTypeMapper::lookupMimeTypeFromPath($file),
        ];

        if ($size > 0) {
            $headers['Content-Length'] = (string) $size;
        }

        return new Response(body: new ReadableResourceStream($fd), headers: $headers);
    }

    private function findFileInPublicDirectory(string $requestPath): string|null
    {
        $path = \realpath($this->dir . $requestPath);

        if ($path === false || !\file_exists($path) || \is_dir($path) || !\str_starts_with($path, $this->dir . '/')) {
            return null;
        }

        return $path;
    }
}
