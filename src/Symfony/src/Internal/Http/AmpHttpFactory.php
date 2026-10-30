<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal\Http;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Http\Server\Response as AmpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function PHPStreamServer\Core\streamOutput;

/**
 * @internal
 */
final class AmpHttpFactory
{
    public function createResponse(SymfonyResponse $symfonyResponse): AmpResponse
    {
        if ($symfonyResponse instanceof BinaryFileResponse && !$symfonyResponse->headers->has('Content-Range')) {
            $path = $symfonyResponse->getFile()->getPathname();
            /** @psalm-suppress PossiblyNullFunctionCall, UndefinedThisPropertyFetch */
            $chunkSize = (fn(): int => $this->chunkSize)->bindTo($symfonyResponse, $symfonyResponse)();
            $body = new ReadableResourceStream(\fopen($path, 'r'), $chunkSize);
        } elseif ($symfonyResponse instanceof BinaryFileResponse) {
            $body = streamOutput(static function () use ($symfonyResponse): void {
                $symfonyResponse->sendContent();
            });
        } elseif ($symfonyResponse instanceof StreamedResponse) {
            $symfonyResponse->headers->remove('Content-Length');
            $callback = $symfonyResponse->getCallback();
            $body = $callback === null ? '' : streamOutput($callback);
        } else {
            $body = (string) $symfonyResponse->getContent();
        }

        /**
         * @var array<non-empty-string, array<array-key, string>|string> $headers
         */
        $headers = $symfonyResponse->headers->all();

        return new AmpResponse(
            status: $symfonyResponse->getStatusCode(),
            headers: $headers,
            body: $body,
        );
    }
}
