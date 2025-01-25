<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\Internal\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Psr\Log\LoggerInterface as PsrLogger;
use Psr\Log\LogLevel;

/**
 * @internal
 */
final readonly class AccessLoggerMiddleware implements Middleware
{
    public function __construct(private PsrLogger $logger)
    {
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $response = $requestHandler->handleRequest($request);

        $remoteAddress = \explode(':', $request->getClient()->getRemoteAddress()->toString())[0];
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $version = $request->getProtocolVersion();
        $code = $response->getStatus();
        $userAgent = $request->getHeader('user-agent') ?? '-';
        $referrer = $request->getHeader('referer') ?? '-';

        $context = [
            'method' => $method,
            'uri' => $uri,
            'version' => $version,
            'remote' => $remoteAddress,
            'code' => $code,
            'user_agent' => $userAgent,
            'referrer' => $referrer,
        ];

        $level = match (true) {
            $code >= 500 => LogLevel::ERROR,
            $code >= 400 => LogLevel::NOTICE,
            default => LogLevel::INFO,
        };

        $this->logger->log($level, \sprintf(
            '%s "%s %s HTTP/%s" %d "%s" "%s"',
            $remoteAddress,
            $method,
            $uri,
            $version,
            $code,
            $referrer,
            $userAgent,
        ), $context);

        return $response;
    }
}
