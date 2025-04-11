<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http\Multipart;

/**
 * @internal
 * @implements \IteratorAggregate<Multipart>
 */
final readonly class MultipartParser implements \IteratorAggregate
{
    private string $contentType;
    private string $boundary;

    /**
     * @param resource $resource
     * @throws InvalidMultipartHeaderException
     */
    public function __construct(private mixed $resource, string|null $contentType)
    {
        [$headerValue, $headerOptions] = Multipart::parseHeaderContent($contentType);
        $this->contentType = $headerValue ?? '';
        $this->boundary = $headerOptions['boundary'] ?? '';

        if ($this->contentType !== 'multipart/form-data') {
            throw new InvalidMultipartHeaderException('Content type must be "multipart/form-data"');
        }

        if ($this->boundary === '') {
            throw new InvalidMultipartHeaderException('Boundary parameter must be included');
        }

        \rewind($resource);
    }

    /**
     * @return \Generator<Multipart>
     * @throws InvalidMultipartContentException
     */
    public function getIterator(): \Generator
    {
        $separator = "--$this->boundary";
        $partCount = 0;
        $partOffset = 0;
        $endOfBody = false;

        while (false !== ($line = \stream_get_line($this->resource, Multipart::BUFFER_SIZE, "\r\n"))) {
            if ($line !== $separator && $line !== "$separator--") {
                continue;
            }

            if ($partOffset > 0) {
                $partCount++;
                $currentOffset = \ftell($this->resource);
                $partStartPosition = $partOffset;
                $partLength = $currentOffset - $partStartPosition - \strlen($line) - 4;

                yield new Multipart($this->resource, $partStartPosition, $partLength);

                \fseek($this->resource, $currentOffset);
            }

            if ($line === "$separator--") {
                $endOfBody = true;
                break;
            }

            $partOffset = \ftell($this->resource);
        }

        if ($partCount === 0 || $endOfBody === false) {
            throw new InvalidMultipartContentException('Can\'t find multipart content');
        }
    }
}
