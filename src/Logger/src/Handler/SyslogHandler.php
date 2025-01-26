<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Handler;

use Amp\Future;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\Logger\AbstractHandler;
use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Formatter\StringFormatter;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;

use function Amp\async;

final class SyslogHandler extends AbstractHandler
{
    private Formatter $formatter;

    /**
     * @see https://www.php.net/manual/en/function.openlog.php
     */
    public function __construct(
        private readonly string $prefix = Server::SHORTNAME,
        private readonly int $flags = 0,
        private readonly int $facility = LOG_USER,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
    ) {
        parent::__construct($level, $channels);
    }

    public function start(): Future
    {
        $this->formatter = new StringFormatter(messageFormat: '{channel}.{level} {message} {context}');
        \openlog($this->prefix, $this->flags, $this->facility);

        return async(static fn() => null);
    }

    public function handle(LogEntry $record): void
    {
        \syslog($record->level->toRFC5424(), $this->formatter->format($record));
    }
}
