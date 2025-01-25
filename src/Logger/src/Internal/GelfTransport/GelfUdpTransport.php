<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\GelfTransport;

use Amp\ByteStream\WritableStream;
use Amp\Socket\DnsSocketConnector;

/**
 * @internal
 */
final readonly class GelfUdpTransport implements GelfTransport
{
    private const MAGIC_BYTES = "\x1e\x0f";
    private const HEADER_SIZE = 12;
    private const CHUNK_SIZE = 8192;
    private const MAX_CHUNKS = 128;
    private const COMPRESS_FROM_SIZE = 4096;

    private WritableStream $socket;

    public function __construct(private string $host, private int $port)
    {
    }

    public function start(): void
    {
        $connector = new DnsSocketConnector();
        $this->socket = $connector->connect(\sprintf('udp://%s:%d', $this->host, $this->port));
    }

    public function write(string $buffer): void
    {
        if (\extension_loaded('zlib') && \strlen($buffer) > self::COMPRESS_FROM_SIZE) {
            $buffer = \gzcompress($buffer, 1, ZLIB_ENCODING_DEFLATE);
        }

        if (\strlen($buffer) <= self::CHUNK_SIZE) {
            $this->socket->write($buffer);
            return;
        }

        $chunks = \str_split($buffer, self::CHUNK_SIZE - self::HEADER_SIZE);
        $chunksCount = \count($chunks);

        if ($chunksCount > self::MAX_CHUNKS) {
            \trigger_error('Message is too big', E_USER_WARNING);
            return;
        }

        $chunkId = \random_bytes(8);
        foreach ($chunks as $chunkIndex => $chunkData) {
            $chunkHeader = self::MAGIC_BYTES . $chunkId . \pack('CC', $chunkIndex, $chunksCount);
            $this->socket->write($chunkHeader . $chunkData);
        }
    }
}
