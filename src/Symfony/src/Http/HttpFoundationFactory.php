<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
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

        $serverAddress = $client->getLocalAddress()->toString();
        $serverAddressDelimiterPosition = (int) \strrpos($serverAddress, ':');
        $serverAddressHost = \substr($serverAddress, 0, $serverAddressDelimiterPosition);

        $remoteAddress = $client->getRemoteAddress()->toString();
        $remoteAddressDelimiterPosition = (int) \strrpos($remoteAddress, ':');
        $remoteAddressHost = \substr($remoteAddress, 0, $remoteAddressDelimiterPosition);
        $remoteAddressPort = \substr($remoteAddress, $remoteAddressDelimiterPosition + 1);

        $server['SERVER_NAME'] = $uri->getHost();
        $server['SERVER_ADDR'] = $serverAddressHost;
        $server['SERVER_PORT'] = $uri->getPort() ?: ('https' === $uri->getScheme() ? 443 : 80);
        $server['REMOTE_ADDR'] = $remoteAddressHost;
        $server['REMOTE_PORT'] = (int) $remoteAddressPort;
        $server['REQUEST_URI'] = $uri->getPath();
        $server['REQUEST_METHOD'] = $request->getMethod();
        $server['QUERY_STRING'] = $uri->getQuery();

        if ($server['QUERY_STRING'] !== '') {
            $server['REQUEST_URI'] .= '?' . $server['QUERY_STRING'];
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
            $content = \trim($request->getBody()->buffer());
            $parsedBody = (array) \json_decode($content, true);
            $parsedFiles = [];
        } elseif ($contentType === 'multipart/form-data') {
            $content = '';
            try {
                [$parsedBody, $parsedFiles] = $this->parseMultiPartPayload($request->getBody(), (string) $request->getHeader('content-type'));
            } catch (InvalidMultipartHeaderException|InvalidMultipartContentException|StreamException) {
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
     * @return array{0: array, 1: array}
     * @throws InvalidMultipartHeaderException
     * @throws InvalidMultipartContentException
     * @throws StreamException
     */
    private function parseMultiPartPayload(ReadableStream $stream, string $contentType): array
    {
        $resource = \fopen('php://temp', 'r+');
        while (null !== $chunk = $stream->read()) {
            \fwrite($resource, $chunk);
        }

        $multipartParser = new MultipartParser($resource, $contentType);

        $payload = [];
        $payloadStructureStr = '';
        $payloadStructureList = [];

        $files = [];
        $fileStructureStr = '';
        $fileStructureList = [];

        foreach ($multipartParser as $part) {
            if (null === $name = $part->getName()) {
                continue;
            }

            if ($part->isFile()) {
                $filename = $part->getFilename();
                if ($filename !== null && $filename !== '') {
                    $fileStructureStr .= "$name&";
                    $fileStructureList[] = new UploadedFile($part);
                }
            } else {
                $payloadStructureStr .= "$name&";
                $payloadStructureList[] = $part->getContents();
            }
        }

        if ($fileStructureList !== []) {
            $i = 0;
            \parse_str($fileStructureStr, $files);
            \array_walk_recursive($files, static function (mixed &$item) use ($fileStructureList, &$i) {
                $item = $fileStructureList[$i++];
            });
        }

        if ($payloadStructureList !== []) {
            $i = 0;
            \parse_str($payloadStructureStr, $payload);
            \array_walk_recursive($payload, static function (mixed &$item) use ($payloadStructureList, &$i) {
                $item = $payloadStructureList[$i++];
            });
        }

        return [$payload, $files];
    }
}
