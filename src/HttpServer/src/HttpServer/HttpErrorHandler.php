<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\HttpServer;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Plugin\HttpServer\Internal\ErrorPage;
use Psr\Log\LoggerInterface;

use function PHPStreamServer\Core\reportErrors;

final readonly class HttpErrorHandler implements ErrorHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handleError(int $status, string|null $reason = null, Request|null $request = null): Response
    {
        $errorPage = new ErrorPage(status: $status, reason: $reason ?? HttpStatus::getReason($status));

        return $this->createResponse($errorPage);
    }

    public function handleException(\Throwable $exception, Request $request): Response
    {
        if ($exception instanceof HttpErrorException) {
            return $this->handleError($exception->getStatus(), $exception->getReason(), $request);
        }

        $this->logException($exception, $request);
        $status = HttpStatus::INTERNAL_SERVER_ERROR;
        $reason = HttpStatus::getReason($status);
        $errorPage = (new ErrorPage(status: $status, reason: $reason, exception: reportErrors() ? $exception : null));

        return $this->createResponse($errorPage);
    }

    private function createResponse(ErrorPage $errorPage): Response
    {
        $response = new Response(
            headers: ['content-type' => 'text/html; charset=utf-8', 'server' => $errorPage->server],
            body: $errorPage->toHtml(),
        );

        $response->setStatus($errorPage->status, $errorPage->reason);

        return $response;
    }

    private function logException(\Throwable $exception, Request $request): void
    {
        $client = $request->getClient();
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $protocolVersion = $request->getProtocolVersion();
        $local = $client->getLocalAddress()->toString();
        $remote = $client->getRemoteAddress()->toString();

        $message = \sprintf(
            'Uncaught %s %s: "%s" in %s:%d during request: %s %s HTTP/%s',
            $exception instanceof \Error ? 'Error' : 'Exception',
            (new \ReflectionClass($exception::class))->getShortName(),
            $exception->getMessage(),
            \basename($exception->getFile()),
            $exception->getLine(),
            $method,
            $uri,
            $protocolVersion,
        );

        $this->logger->critical($message, [
            'exception' => $exception,
            'method' => $method,
            'uri' => $uri,
            'local' => $local,
            'remote' => $remote,
        ]);
    }
}
