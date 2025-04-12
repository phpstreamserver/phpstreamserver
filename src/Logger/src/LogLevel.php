<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

enum LogLevel: int
{
    case DEBUG = 1;
    case INFO = 2;
    case NOTICE = 3;
    case WARNING = 4;
    case ERROR = 5;
    case CRITICAL = 6;
    case ALERT = 7;
    case EMERGENCY = 8;

    public static function fromString(string $name): self
    {
        return match (\strtolower($name)) {
            'debug' => self::DEBUG,
            'info' => self::INFO,
            'notice' => self::NOTICE,
            'warning' => self::WARNING,
            'error' => self::ERROR,
            'critical' => self::CRITICAL,
            'alert' => self::ALERT,
            'emergency' => self::EMERGENCY,
            default => self::CRITICAL,
        };
    }

    public function toString(): string
    {
        return match ($this) {
            self::DEBUG => 'debug',
            self::INFO => 'info',
            self::NOTICE => 'notice',
            self::WARNING => 'warning',
            self::ERROR => 'error',
            self::CRITICAL => 'critical',
            self::ALERT => 'alert',
            self::EMERGENCY => 'emergency',
        };
    }

    public static function fromRFC5424(int $level): self
    {
        return match ($level) {
            7 => self::DEBUG,
            6 => self::INFO,
            5 => self::NOTICE,
            4 => self::WARNING,
            3 => self::ERROR,
            2 => self::CRITICAL,
            1 => self::ALERT,
            0 => self::EMERGENCY,
        };
    }

    public function toRFC5424(): int
    {
        return match ($this) {
            self::DEBUG => 7,
            self::INFO => 6,
            self::NOTICE => 5,
            self::WARNING => 4,
            self::ERROR => 3,
            self::CRITICAL => 2,
            self::ALERT => 1,
            self::EMERGENCY => 0,
        };
    }
}
