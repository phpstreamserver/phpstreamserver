<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Formatter;

use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenDateTime;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenEnum;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenException;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenObject;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenResource;
use PHPStreamServer\Plugin\Logger\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;

final readonly class ConsoleFormatter implements Formatter
{
    private const ARRAY_BRACES_COLOR = 202;
    private const ARRAY_KEY_COLOR = 113;
    private const SCALAR_COLOR = 38;
    private const OBJECT_COLOR = 37;
    private const EXCEPTION_COLOR = 167;

    private const LEVEL_MAP = [
        LogLevel::DEBUG->value => '<color;fg=245>DEBUG</> ',
        LogLevel::INFO->value => '<color;fg=77>INFO</>  ',
        LogLevel::NOTICE->value => '<color;fg=75>NOTICE</>',
        LogLevel::WARNING->value => '<color;fg=214>WARN</>  ',
        LogLevel::ERROR->value => '<color;fg=203>ERROR</> ',
        LogLevel::CRITICAL->value => '<color;fg=203>CRIT</>  ',
        LogLevel::ALERT->value => '<color;fg=196>ALERT</> ',
        LogLevel::EMERGENCY->value => '<color;fg=15;bg=196>EMERG</> ',
    ];

    public function __construct(
        private string $dateTimeFormat = 'Y-m-d H:i:s',
    ) {
    }

    public function format(LogEntry $record): string
    {
        $errorLevel = self::LEVEL_MAP[$record->level->value] ?? $record->level->value;

        $body = \sprintf(
            "%s %s <color;fg=token>%s</> › %s",
            $record->time->format($this->dateTimeFormat),
            $errorLevel,
            $record->channel,
            $record->message,
        );

        if ($record->context !== []) {
            $body .= ' ' . $this->formatArrayAsString($record->context);
        }

        return $body;
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
                $data->__toString(),
            );
        }

        if ($data instanceof FlattenDateTime) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->withFormat($this->dateTimeFormat)->__toString());
        }

        if ($data instanceof FlattenObject) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->__toString());
        }

        if ($data instanceof FlattenEnum) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->__toString());
        }

        if ($data instanceof FlattenResource) {
            return \sprintf('<color;fg=%s>%s</>', self::OBJECT_COLOR, $data->__toString());
        }

        return 'unknown';
    }
}
