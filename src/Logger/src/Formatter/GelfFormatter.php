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
 * Serializes a log record to GELF format
 * @see https://go2docs.graylog.org/current/getting_in_log_data/gelf.html
 */
final readonly class GelfFormatter implements Formatter
{
    private const VERSION = '1.1';

    public const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
    ;

    private string $hostName;

    public function __construct(string $hostName = null, private bool $includeStacktraces = false)
    {
        $this->hostName = $hostName ?? \gethostname();
    }

    public function format(LogEntry $record): string
    {
        $message = [
            'version' => self::VERSION,
            'host' => $this->hostName,
            'short_message' => $record->message,
            'full_message' => null,
            'timestamp' => $record->time->format('U.u'),
            'level' => $record->level->toRFC5424(),
            '_channel' => $record->channel,
        ];

        foreach ($record->context as $contextKey => $contextData) {
            if ($this->includeStacktraces && $contextData instanceof FlattenException && $message['full_message'] === null) {
                $message['full_message'] = $contextData->getFullTextMessageWithTrace();
            }

            $message['_' . $contextKey] = $this->normalize($contextData);
        }

        return \json_encode(\array_filter($message, static function (mixed $data) {
            return !(\is_bool($data) || \is_null($data) || $data === '');
        }), self::DEFAULT_JSON_FLAGS);
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
