<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Http\Server\Response as AmpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AmpHttpFactory
{
    public function createResponse(SymfonyResponse $symfonyResponse): AmpResponse
    {
        if ($symfonyResponse instanceof BinaryFileResponse && !$symfonyResponse->headers->has('Content-Range')) {
            $path = $symfonyResponse->getFile()->getPathname();
            $body = new ReadableResourceStream(\fopen($path, 'r'));
        } else if ($symfonyResponse instanceof StreamedResponse || $symfonyResponse instanceof BinaryFileResponse) {
            $resource = \fopen('php://temp', 'r+');
            \ob_start(static function (string $buffer) use ($resource): string {
                \fwrite($resource, $buffer);
                return '';
            }, 1);
            $symfonyResponse->sendContent();
            \ob_end_clean();
            \rewind($resource);
            $body = new ReadableResourceStream($resource);
        } else {
            $body = (string) $symfonyResponse->getContent();
        }

        /**
         * @var array<non-empty-string, array<array-key, string>|string> $headers
         */
        $headers = $symfonyResponse->headers->all();
        $cookies = $symfonyResponse->headers->getCookies();
        if ($cookies !== []) {
            $headers['Set-Cookie'] = [];
            foreach ($cookies as $cookie) {
                $headers['Set-Cookie'][] = $cookie->__toString();
            }
        }

        return new AmpResponse(
            status: $symfonyResponse->getStatusCode(),
            headers: $headers,
            body: $body,
        );
    }
}
