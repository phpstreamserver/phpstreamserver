<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

use Psr\Log\LogLevel as PsrLogLevel;

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

    public static function fromName(string $name): self
    {
        return match (\strtolower($name)) {
            PsrLogLevel::DEBUG => self::DEBUG,
            PsrLogLevel::INFO => self::INFO,
            PsrLogLevel::NOTICE => self::NOTICE,
            PsrLogLevel::WARNING => self::WARNING,
            PsrLogLevel::ERROR => self::ERROR,
            PsrLogLevel::CRITICAL => self::CRITICAL,
            PsrLogLevel::ALERT => self::ALERT,
            PsrLogLevel::EMERGENCY => self::EMERGENCY,
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
