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
use PHPStreamServer\Plugin\Logger\LogLevel;

final readonly class ConsoleFormatter implements Formatter
{
    private const ARRAY_BRACES_COLOR = 202;
    private const ARRAY_KEY_COLOR = 113;
    private const SCALAR_COLOR = 38;
    private const OBJECT_COLOR = 37;
    private const EXCEPTION_COLOR = 167;

    private const LEVEL_COLOR_MAP = [
        LogLevel::DEBUG->value => 'fg=15',
        LogLevel::INFO->value => 'fg=116',
        LogLevel::NOTICE->value => 'fg=38',
        LogLevel::WARNING->value => 'fg=yellow',
        LogLevel::ERROR->value => 'fg=red',
        LogLevel::CRITICAL->value => 'fg=red',
        LogLevel::ALERT->value => 'fg=red',
        LogLevel::EMERGENCY->value => 'bg=red',
    ];

    public function __construct(
        private string $dateTimeFormat = \DateTimeInterface::RFC3339,
    ) {
    }

    public function format(LogEntry $record): string
    {
        $time = $record->time->format($this->dateTimeFormat);

        $body = \sprintf(
            "[%s] <color;fg=green>%s</>.<color;%s>%s</> %s",
            $time,
            $record->channel,
            self::LEVEL_COLOR_MAP[$record->level->value] ?? 'fg=gray',
            \strtoupper($record->level->toString()),
            $record->message,
        );

        $context = '';
        if ($record->context !== []) {
            $context = $this->formatArrayAsString($record->context);
        }

        return \rtrim($body . ' ' . $context);
    }

    private function formatArrayAsString(array $array): string
    {
        $result = [];
        foreach ($array as $key => $value) {
            $formattedValue = \is_array($value) ? $this->formatArrayAsString($value) : $this->formatValueAsString($value);
            $result[] = \sprintf('<color;fg=%s>"%s"</>: %s', self::ARRAY_KEY_COLOR, $key, $formattedValue);
        }

        return \sprintf('<color;fg=%s>[</>%s<color;fg=%s>]</>', self::ARRAY_BRACES_COLOR, \implode(',', $result), self::ARRAY_BRACES_COLOR);
    }

    private function formatValueAsString(mixed $data): string
    {
        if ($data === null) {
            return \sprintf('<color;fg=%s>null</>', self::SCALAR_COLOR);
        }

        if ($data === false) {
            return \sprintf('<color;fg=%s>false</>', self::SCALAR_COLOR);
        }

        if ($data === true) {
            return \sprintf('<color;fg=%s>true</>', self::SCALAR_COLOR);
        }

        if (\is_string($data)) {
            return \sprintf('<color;fg=%s>"%s"</>', self::SCALAR_COLOR, $data);
        }

        if (\is_scalar($data)) {
            return \sprintf('<color;fg=%s>%s</>', self::SCALAR_COLOR, (string) $data);
        }

        if ($data instanceof FlattenException) {
            return \str_replace(
                ['[exception(', '[previous(', ']: '],
                ['<color;fg=' . self::EXCEPTION_COLOR . '>[exception(', '<color;fg=' . self::EXCEPTION_COLOR . '>[previous(', ']</>: '],
                $data->toString(),
            );
        }

        if ($data instanceof FlattenDateTime) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->toString($this->dateTimeFormat));
        }

        if ($data instanceof FlattenObject) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->toString());
        }

        if ($data instanceof FlattenEnum) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->toString());
        }

        if ($data instanceof FlattenResource) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->toString());
        }

        return 'unknown';
    }
}
