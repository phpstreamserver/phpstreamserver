<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http\Multipart;

/**
 * @internal
 */
final class Multipart
{
    public const BUFFER_SIZE = 32768;

    private readonly int $offset;
    private readonly int $size;
    private readonly int $headersSize;
    private int $pointer = 0;
    private array $headers = [];
    private array $headerOptionsCache = [];

    /**
     * @param resource $stream
     */
    public function __construct(private readonly mixed $stream, int $offset, int $size)
    {
        \fseek($this->stream, $offset);
        $this->offset = $offset;
        $this->size = $size;
        $endOfHeaders = false;

        while (false !== ($line = \stream_get_line($this->stream, self::BUFFER_SIZE, "\r\n"))) {
            // Empty line cause by double new line, we reached the end of the headers section
            if ($line === '') {
                $this->headersSize = \ftell($this->stream) - $offset - 2;
                $endOfHeaders = true;
                break;
            }
            $parts = \explode(':', $line, 2);
            if (\count($parts) !== 2) {
                continue;
            }
            $key = \strtolower($parts[0]);
            $value = \trim($parts[1]);
            $this->headers[$key] = isset($this->headers[$key]) ? "{$this->headers[$key]}, $value" : $value;
        }

        if ($endOfHeaders === false) {
            throw new \InvalidArgumentException('Header is not valid');
        }
    }

    public function getHeader(string $key, string|null $default = null): string|null
    {
        return $this->headers[\strtolower($key)] ?? $default;
    }

    public function getHeaderValue(string $key, string|null $default = null): string|null
    {
        return ($this->headerOptionsCache[$key] ??= self::parseHeaderContent($this->getHeader($key)))[0] ?? $default;
    }

    public function getHeaderOption(string $key, string $option, string|null $default = null): string|null
    {
        $headerOptions = ($this->headerOptionsCache[$key] ??= self::parseHeaderContent($this->getHeader($key)))[1];

        return $headerOptions[$option] ?? $default;
    }

    public function isFile(): bool
    {
        return $this->getHeaderOption('Content-Disposition', 'filename') !== null;
    }

    public function getFileName(): string|null
    {
        return $this->getHeaderOption('Content-Disposition', 'filename');
    }

    public function getName(): string|null
    {
        return $this->getHeaderOption('Content-Disposition', 'name');
    }

    public static function parseHeaderContent(string|null $content): array
    {
        if ($content !== null) {
            \parse_str(\str_replace('+', '%2B', \preg_replace('/;\s?/', '&', $content)), $values);
            $values = \array_map(static fn (string $v) => \trim($v, '"'), $values);
            if (($firstKey = \array_key_first($values)) !== null && $values[$firstKey] === '') {
                \array_shift($values);
                $headerValue = $firstKey;
            }
        }

        return [$headerValue ?? null, $values ?? []];
    }

    public function getSize(): int
    {
        return $this->size - $this->headersSize - 2;
    }

    public function tell(): int
    {
        return $this->pointer;
    }

    public function eof(): bool
    {
        return \feof($this->stream) || $this->tell() >= $this->getSize();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence === SEEK_CUR) {
            $offset += $this->pointer;
        } elseif ($whence === SEEK_END) {
            $offset += $this->getSize();
        }

        if ($offset > $this->getSize()) {
            $offset = $this->getSize();
        }

        $this->pointer = $offset;
        $globalOffset = $offset + $this->offset + $this->headersSize + 2;
        \fseek($this->stream, $globalOffset);
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function read(int $length): string
    {
        $remainingSize = $this->getSize() - $this->tell();

        if ($length > $remainingSize) {
            $length = $remainingSize;
        }

        $this->pointer += $length;

        if ($length === 0) {
            return '';
        }

        return \fread($this->stream, $length);
    }

    public function getContents(): string
    {
        try {
            return \stream_get_contents($this->stream, $this->getSize(), $this->offset + $this->headersSize + 2);
        } finally {
            $this->seek(0, SEEK_END);
        }
    }
}
