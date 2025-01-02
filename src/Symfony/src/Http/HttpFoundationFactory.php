<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\Http\Server\Request as AmpRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class HttpFoundationFactory
{
    public function createRequest(AmpRequest $ampRequest): SymfonyRequest
    {
        $server = [];
        $uri = $ampRequest->getUri();
        $client = $ampRequest->getClient();
        $serverAddress = \explode(':', $client->getLocalAddress()->toString(), 2);
        $remoteAddress = \explode(':', $client->getRemoteAddress()->toString(), 2);

        $server['SERVER_NAME'] = $uri->getHost();
        $server['SERVER_ADDR'] = $serverAddress[0];
        $server['SERVER_PORT'] = $uri->getPort() ?: ('https' === $uri->getScheme() ? 443 : 80);
        $server['REMOTE_ADDR'] = $remoteAddress[0];
        $server['REMOTE_PORT'] = (int) $remoteAddress[1];
        $server['REQUEST_URI'] = $uri->getPath();
        $server['REQUEST_METHOD'] = $ampRequest->getMethod();
        $server['QUERY_STRING'] = $uri->getQuery();

        if ($server['QUERY_STRING'] !== '') {
            $server['REQUEST_URI'] .= '?'.$server['QUERY_STRING'];
        }

        if ($uri->getScheme() === 'https') {
            $server['HTTPS'] = 'on';
        }

        $query = [];
        \parse_str($uri->getQuery(), $query);

        $cookies = [];
        foreach ($ampRequest->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        $symfonyRequest = new SymfonyRequest(
            query: $query,
            request: [], // @TODO
            attributes: $ampRequest->getAttributes(),
            cookies: $cookies,
            files: [], // @TODO
            server: $server,
            content: null, // @TODO
        );

        $symfonyRequest->headers->add($ampRequest->getHeaders());

        return $symfonyRequest;
    }
}
