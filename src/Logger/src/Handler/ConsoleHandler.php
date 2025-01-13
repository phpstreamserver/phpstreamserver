<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Handler;

use Amp\ByteStream\WritableResourceStream;
use Amp\Future;
use PHPStreamServer\Core\Console\Colorizer;
use PHPStreamServer\Plugin\Logger\AbstractHandler;
use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Formatter\ConsoleFormatter;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;
use function PHPStreamServer\Core\getStderr;
use function PHPStreamServer\Core\getStdout;

final class ConsoleHandler extends AbstractHandler
{
    public const OUTPUT_STDOUT = 1;
    public const OUTPUT_STDERR = 2;

    private WritableResourceStream $stream;
    private bool $colorSupport;

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
        $this->stream = $this->output === self::OUTPUT_STDERR ? getStderr() : getStdout();
        /** @psalm-suppress PossiblyInvalidArgument */
        $this->colorSupport = Colorizer::hasColorSupport($this->stream->getResource());

        return Future::complete();
    }

    public function handle(LogEntry $record): void
    {
        $message = $this->formatter->format($record);
        $message = $this->colorSupport ? Colorizer::colorize($message) : Colorizer::stripTags($message);
        $this->stream->write($message . "\n");
    }
}
