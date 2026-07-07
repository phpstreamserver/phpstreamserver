<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Formatter;

use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenDateTime;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenEnum;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenException;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenObject;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenResource;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;

/**
 * Serializes a log record to JSON
 */
final readonly class JsonFormatter implements Formatter
{
    public const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
    ;

    public function __construct(
        private bool $ignoreEmptyContext = false,
        private bool $includeStacktraces = false,
    ) {
    }

    public function format(LogEntry $record): string
    {
        $normalized = [
            'message' => $record->message,
            'context' => $this->normalize($record->context),
            'level' => $record->level->value,
            'level_name' => $record->level->name,
            'channel' => $record->channel,
            'datetime' => $record->time->format(\DateTimeInterface::RFC3339),
        ];

        if ($this->ignoreEmptyContext && $normalized['context'] === []) {
            unset($normalized['context']);
        }

        return \json_encode($normalized, self::DEFAULT_JSON_FLAGS);
    }

    private function normalize(mixed $data): mixed
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->normalize($value);
            }

            return $data;
        }

        if (\is_null($data) || \is_scalar($data)) {
            return $data;
        }

        if ($data instanceof FlattenException) {
            return $this->normalizeException($data);
        }

        if ($data instanceof FlattenDateTime) {
            return $data->dt->format(\DateTimeInterface::RFC3339);
        }

        if ($data instanceof FlattenObject) {
            return \trim($data->__toString(), '[]');
        }

        if ($data instanceof FlattenResource) {
            return \trim($data->__toString(), '[]');
        }

        if ($data instanceof FlattenEnum) {
            return \trim($data->__toString(), '[]');
        }

        return 'unknown';
    }

    protected function normalizeException(FlattenException $exception): array
    {
        $data = [
            'class' => $exception->class,
            'message' => $exception->message,
            'code' => $exception->code,
            'file' => $exception->file . ':' . $exception->line,
        ];

        if ($this->includeStacktraces) {
            foreach ($exception->trace as $frame) {
                if (isset($frame['file'], $frame['line'])) {
                    $data['trace'][] = $frame['file'] . ':' . $frame['line'];
                }
            }
        }

        if (($previous = $exception->previous) instanceof FlattenException) {
            $data['previous'] = $this->normalizeException($previous);
        }

        return $data;
    }
}
