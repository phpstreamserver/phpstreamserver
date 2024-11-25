<?php

declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

use PHPStreamServer\Server;
use PHPStreamServer\WorkerProcess_OLD;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

$tempFiles = [];
$streamResponse = \fopen('php://temp', 'rw');
\fwrite($streamResponse, 'ok-answer from stream');
$server = new Server();
$server->addWorkerProcess(
    new WorkerProcess_OLD(
        name: 'Worker 1',
        count: 1,
    ),
    new WorkerProcess_OLD(
        name: 'Worker 2',
        count: 2,
    ),
    new WorkerProcess_OLD(
        name: 'HTTP Server',
        count: 2,
        onStart: static function (WorkerProcess_OLD $worker) use (&$tempFiles, $streamResponse) {
            $worker->startListener(new Listener(
                listen: 'tcp://0.0.0.0:9080',
                protocol: new Http(),
                onClose: static function () use (&$tempFiles) {
                    foreach ($tempFiles as $tempFile) {
                        \is_file($tempFile) && \unlink($tempFile);
                    }
                },
                onMessage: static function (ConnectionInterface $connection, ServerRequestInterface $data) use (&$tempFiles, $streamResponse): void {
                    $files = $data->getUploadedFiles();
                    \array_walk_recursive($files, static function (UploadedFileInterface &$file) use (&$tempFiles) {
                        $tmpFile = \sys_get_temp_dir() . '/' . \uniqid('test');
                        $tempFiles[] = $tmpFile;
                        $file->moveTo($tmpFile);
                        $file = [
                            'client_filename' => $file->getClientFilename(),
                            'client_media_type' => $file->getClientMediaType(),
                            'size' => $file->getSize(),
                            'sha1' => \hash_file('sha1', $tmpFile),
                        ];
                    });
                    $response = match ($data->getUri()->getPath()) {
                        '/ok1' => new Response(
                            body: 'ok-answer',
                            headers: ['Content-Type' => 'text/plain'],
                        ),
                        '/ok2' => new Response(
                            body: $streamResponse,
                            headers: ['Content-Type' => 'text/plain'],
                        ),
                        '/request' => new Response(
                            body: \json_encode([
                                'server_params' => $data->getServerParams(),
                                'headers' => $data->getHeaders(),
                                'query' => $data->getQueryParams(),
                                'request' => $data->getParsedBody(),
                                'files' => $files,
                                'cookies' => $data->getCookieParams(),
                                'raw_request' => empty($files) ? $data->getBody()->getContents() : '',
                            ]),
                            headers: ['Content-Type' => 'application/json'],
                        ),
                        default => throw HttpException::createNotFoundException(),
                    };
                    $connection->send($response);
                },
            ));
            $worker->startListener(new Listener(
                listen: 'tcp://0.0.0.0:9086',
                protocol: new Http(
                    maxBodySize: 102400,
                ),
                onMessage: static function (ConnectionInterface $connection, ServerRequestInterface $data): void {
                    $connection->send(new Response(body: 'ok', headers: ['Content-Type' => 'text/plain']));
                },
            ));
        },
    ),
    new WorkerProcess_OLD(
        name: 'HTTPS Server',
        count: 1,
        onStart: static function (WorkerProcess_OLD $worker) {
            $worker->startListener(new Listener(
                listen: 'tcp://127.0.0.1:9081',
                tls: true,
                tlsCertificate: __DIR__ . '/localhost.crt',
                protocol: new Http(),
                onMessage: static function (ConnectionInterface $connection, ServerRequestInterface $data): void {
                    $connection->send(new Response(
                        body: 'ok-answer-tls',
                        headers: ['Content-Type' => 'text/plain'],
                    ));
                },
            ));
        },
    ),
);
exit($server->run());
