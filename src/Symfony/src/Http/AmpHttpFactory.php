<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\ByteStream\ReadableIterableStream;
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
        } elseif ($symfonyResponse instanceof BinaryFileResponse) {
            $callback = static function () use ($symfonyResponse): void {
                $symfonyResponse->sendContent();
            };
            $body = new ReadableIterableStream((new OutputStreamIterator($callback))->getIterator());
        } elseif ($symfonyResponse instanceof StreamedResponse) {
            $callback = $symfonyResponse->getCallback();
            $body = $callback === null
                ? ''
                : new ReadableIterableStream((new OutputStreamIterator($callback))->getIterator());
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
