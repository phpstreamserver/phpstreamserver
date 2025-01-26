<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Handler;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\Future;
use PHPStreamServer\Plugin\Logger\AbstractHandler;
use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Formatter\StringFormatter;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;
use Revolt\EventLoop;

use function Amp\async;
use function Amp\delay;

final class FileHandler extends AbstractHandler
{
    private bool $pause = true;
    private \SplFileInfo $logFile;
    private WritableResourceStream $stream;

    /**
     * @param string $filename Log file name
     * @param bool $rotate Rotate log files
     * @param bool $compress gzip archived log files
     * @param int $maxFiles The maximal amount of files to keep (0 means unlimited)
     * @param int $permission Permissions for created files
     */
    public function __construct(
        private readonly string $filename,
        private readonly bool $rotate = false,
        private readonly bool $compress = false,
        private readonly int $maxFiles = 0,
        private readonly int $permission = 0644,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
        private readonly Formatter $formatter = new StringFormatter(),
    ) {
        if ($compress && !\extension_loaded('zlib')) {
            throw new \RuntimeException('Install zlib extension to use compression');
        }

        parent::__construct($level, $channels);
    }

    public function start(): Future
    {
        return async(function () {
            $file = !\str_starts_with($this->filename, '/') ? \getcwd() . '/' . $this->filename : $this->filename;
            $this->logFile = new \SplFileInfo($file);

            if (!\is_dir($this->logFile->getPath())) {
                \mkdir(directory: $this->logFile->getPath(), recursive: true);
            }

            $this->stream = new WritableResourceStream(\fopen($this->logFile->getPathname(), 'a'));
            \chmod($this->logFile->getPathname(), $this->permission);

            if ($this->rotate) {
                $this->scheduleRotate();
            }

            $this->pause = false;
        });
    }

    private function scheduleRotate(): void
    {
        $currentTime = new \DateTimeImmutable('now');
        $nextRotation = new \DateTimeImmutable('tomorrow');
        $delay = $nextRotation->getTimestamp() - $currentTime->getTimestamp();
        EventLoop::delay($delay, function () use ($nextRotation): void {
            $this->rotate($nextRotation);
            $this->scheduleRotate();
        });
    }

    private function rotate(\DateTimeImmutable $rotationDate): void
    {
        $this->pause = true;
        $this->archiveLogFile($rotationDate);
        $this->pause = false;

        $archivedFilesGlobPattern = \sprintf(
            '%s/%s-*.%s',
            $this->logFile->getPath(),
            $this->logFile->getBasename('.' . $this->logFile->getExtension()),
            $this->logFile->getExtension(),
        );

        if ($this->maxFiles > 0) {
            $this->removeOldLogFiles($archivedFilesGlobPattern);
        }

        if ($this->compress) {
            $this->gzipLogFiles($archivedFilesGlobPattern);
        }
    }

    private function archiveLogFile(\DateTimeImmutable $date): void
    {
        $timedLogFileName = \sprintf(
            '%s/%s-%s.%s',
            $this->logFile->getPath(),
            $this->logFile->getBasename('.' . $this->logFile->getExtension()),
            $date->format('Y-m-d'),
            $this->logFile->getExtension(),
        );

        $this->stream->close();
        \rename($this->logFile->getPathname(), $timedLogFileName);

        $this->stream = new WritableResourceStream(\fopen($this->logFile->getPathname(), 'a'));
        \chmod($this->logFile->getPathname(), $this->permission);
    }

    private function removeOldLogFiles(string $globPattern): void
    {
        if (false === $logFiles = \glob($globPattern . '*')) {
            return;
        }

        $count = \count($logFiles);
        foreach ($logFiles as $logFile) {
            if ($count-- <= $this->maxFiles) {
                break;
            }
            \unlink($logFile);
        }
    }

    private function gzipLogFiles(string $globPattern): void
    {
        if (false === $logFiles = \glob($globPattern)) {
            return;
        }

        foreach ($logFiles as $logFile) {
            $source = new ReadableResourceStream(\fopen($logFile, 'rb'));
            $destination = new WritableResourceStream(\gzopen($logFile . '.gz', 'wb'));

            \chmod($logFile . '.gz', $this->permission);

            while (null !== $chunk = $source->read(limit: 32768)) {
                $destination->write($chunk);
                unset($chunk);
            }

            unset($source);
            unset($destination);
            \unlink($logFile);
        }
    }

    public function handle(LogEntry $record): void
    {
        while ($this->pause) {
            delay(0.01);
        }

        $this->stream->write($this->formatter->format($record) . "\n");
    }
}
