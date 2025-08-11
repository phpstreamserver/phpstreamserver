<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Handler;

use Amp\Future;
use PHPStreamServer\Core\Internal\Console\StdoutHandler;
use PHPStreamServer\Plugin\Logger\AbstractHandler;
use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Formatter\ConsoleFormatter;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;

final class ConsoleHandler extends AbstractHandler
{
    public const OUTPUT_STDOUT = 1;
    public const OUTPUT_STDERR = 2;

    private \Closure $stdHandler;

    public function __construct(
        private readonly int $output = self::OUTPUT_STDERR,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
        private readonly Formatter $formatter = new ConsoleFormatter(),
    ) {
        parent::__construct($level, $channels);
    }

    public function start(): Future
    {
        $this->stdHandler = $this->output === self::OUTPUT_STDERR ? StdoutHandler::stderr(...) : StdoutHandler::stdout(...);

        return Future::complete();
    }

    public function handle(LogEntry $record): void
    {
        $message = $this->formatter->format($record);
        $this->stdHandler->__invoke($message . "\n");
    }
}
