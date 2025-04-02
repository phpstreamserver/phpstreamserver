<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Handler;

use Amp\Future;
use PHPStreamServer\Plugin\Logger\AbstractHandler;
use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Formatter\GelfFormatter;
use PHPStreamServer\Plugin\Logger\Internal\GelfTransport\GelfHttpTransport;
use PHPStreamServer\Plugin\Logger\Internal\GelfTransport\GelfTcpTransport;
use PHPStreamServer\Plugin\Logger\Internal\GelfTransport\GelfTransport;
use PHPStreamServer\Plugin\Logger\Internal\GelfTransport\GelfUdpTransport;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;
use Revolt\EventLoop;

use function Amp\async;

final class GelfHandler extends AbstractHandler
{
    private Formatter $formatter;
    private GelfTransport $transport;

    /**
     * @param string $address Gelf server address. Must begin with one of the following schemes: udp://, tcp://, http://, https://
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $address,
        string|null $hostName = null,
        bool $includeStacktraces = false,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
    ) {
        [$scheme, $host, $port] = $this->parseAddress($address);
        $this->formatter = new GelfFormatter($hostName, $includeStacktraces);
        $this->transport = match ($scheme) {
            'udp' => new GelfUdpTransport($host, $port),
            'tcp' => new GelfTcpTransport($host, $port),
            'http', 'https' => new GelfHttpTransport($address),
        };
        parent::__construct($level, $channels);
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     * @throws \InvalidArgumentException
     */
    private function parseAddress(string $address): array
    {
        if (
            !\str_starts_with($address, 'udp://') &&
            !\str_starts_with($address, 'tcp://') &&
            !\str_starts_with($address, 'http://') &&
            !\str_starts_with($address, 'https://')
        ) {
            throw new \InvalidArgumentException('Invalid address format: must begin with one of the following schemes: udp://, tcp://, http://, https://');
        }

        $parts = \parse_url($address);
        if (!isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Invalid address format: missing host');
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = $parts['port'] ?? match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => throw new \InvalidArgumentException('Invalid address format: missing port number'),
        };

        return [$scheme, $host, $port];
    }

    public function start(): Future
    {
        return async(function () {
            $this->transport->start();
        });
    }

    public function handle(LogEntry $record): void
    {
        EventLoop::queue(function () use ($record) {
            $this->transport->write($this->formatter->format($record));
        });
    }
}
