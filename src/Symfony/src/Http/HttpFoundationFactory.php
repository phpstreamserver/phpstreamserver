<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Server\Request as AmpRequest;
use PHPStreamServer\Symfony\Http\Multipart\InvalidMultipartContentException;
use PHPStreamServer\Symfony\Http\Multipart\InvalidMultipartHeaderException;
use PHPStreamServer\Symfony\Http\Multipart\Multipart;
use PHPStreamServer\Symfony\Http\Multipart\MultipartParser;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class HttpFoundationFactory
{
    public function createRequest(AmpRequest $request): SymfonyRequest
    {
        $server = [];
        $uri = $request->getUri();
        $client = $request->getClient();
        $serverAddress = \explode(':', $client->getLocalAddress()->toString(), 2);
        $remoteAddress = \explode(':', $client->getRemoteAddress()->toString(), 2);

        $server['SERVER_NAME'] = $uri->getHost();
        $server['SERVER_ADDR'] = $serverAddress[0];
        $server['SERVER_PORT'] = $uri->getPort() ?: ('https' === $uri->getScheme() ? 443 : 80);
        $server['REMOTE_ADDR'] = $remoteAddress[0];
        $server['REMOTE_PORT'] = (int) $remoteAddress[1];
        $server['REQUEST_URI'] = $uri->getPath();
        $server['REQUEST_METHOD'] = $request->getMethod();
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
        foreach ($request->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        [$content, $parsedBody, $parsedFiles] = $this->parsePayload($request);

        $symfonyRequest = new SymfonyRequest(
            query: $query,
            request: $parsedBody,
            attributes: $request->getAttributes(),
            cookies: $cookies,
            files: $parsedFiles,
            server: $server,
            content: $content,
        );

        $symfonyRequest->headers->add($request->getHeaders());

        return $symfonyRequest;
    }

    /**
     * @return array{0: string, 1: array, 2: array}
     */
    private function parsePayload(AmpRequest $request): array
    {
        $contentType = Multipart::parseHeaderContent($request->getHeader('content-type'))[0];

        if (!\in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            $content = '';
            $parsedBody = [];
            $parsedFiles = [];
        } elseif ($contentType === 'application/x-www-form-urlencoded') {
            $content = \trim($request->getBody()->buffer());
            $parsedBody = [];
            $parsedFiles = [];
            \parse_str(\urldecode($content), $parsedBody);
        } elseif ($contentType === 'application/json') {
            $content = $request->getBody()->buffer();
            $parsedBody = (array) \json_decode($content, true);
            $parsedFiles = [];
        } elseif ($contentType === 'multipart/form-data') {
            $content = '';
            try {
                $multipartParser = new MultipartParser($this->createTempResource($request->getBody()), $request->getHeader('content-type'));
                [$parsedBody, $parsedFiles] = $this->parseMultiPartParts($multipartParser->getParts());
            } catch (InvalidMultipartHeaderException|InvalidMultipartContentException) {
                $parsedBody = [];
                $parsedFiles = [];
            }
        } else {
            $content = '';
            $parsedBody = [];
            $parsedFiles = [];
        }

        return [$content, $parsedBody, $parsedFiles];
    }

    /**
     * @return resource
     */
    private function createTempResource(ReadableStream $stream): mixed
    {
        $resource = \fopen('php://temp', 'r+');
        while (null !== $chunk = $stream->read()) {
            \fwrite($resource, $chunk);
        }
        \rewind($resource);

        return $resource;
    }

    /**
     * @param \Generator<Multipart> $parts
     * @return array{0: array, 1: array}
     */
    private function parseMultiPartParts(\Generator $parts): array
    {
        $payload = [];
        $files = [];
        $fileStructureStr = '';
        $fileStructureList = [];
        foreach ($parts as $part) {
            /** @var Multipart $part */
            if (null === $name = $part->getName()) {
                continue;
            }

            if ($part->isFile()) {
                $fileStructureStr .= "$name&";
                $fileStructureList[] = new UploadedFile($part);
            } else {
                $payload[$name] = $part->getContents();
            }
        }
        if (!empty($fileStructureList)) {
            $i = 0;
            \parse_str($fileStructureStr, $files);
            \array_walk_recursive($files, static function (mixed &$item) use ($fileStructureList, &$i) {
                $item = $fileStructureList[$i++];
            });
        }

        return [$payload, $files];
    }

}
