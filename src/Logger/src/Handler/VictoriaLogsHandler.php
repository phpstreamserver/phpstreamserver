<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Handler;

use Amp\ByteStream\ReadableIterableStream;
use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\StreamedContent;
use PHPStreamServer\Plugin\Logger\AbstractHandler;
use PHPStreamServer\Plugin\Logger\Formatter;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenDateTime;
use PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer\FlattenException;
use PHPStreamServer\Plugin\Logger\LogEntry;
use PHPStreamServer\Plugin\Logger\LogLevel;
use Revolt\EventLoop;

use function Amp\async;

final class VictoriaLogsHandler extends AbstractHandler
{
    private const REQUEST_TIMEOUT = 3.0;
    private const MAX_BUFFER_SIZE = 1_048_576; // 1 MiB

    private HttpClient $httpClient;
    private Request $requestTemplate;
    private readonly Formatter $formatter;
    /** @var list<string> */
    private array $buffer = [];
    private int $bufferSize = 0;
    private bool $pendingRequest = false;

    /**
     * @param list<string> $streamFields
     */
    public function __construct(
        private readonly string $baseUri,
        string $appName = 'app',
        private readonly array $streamFields = ['app', 'channel'],
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
    ) {
        $uri = \parse_url($baseUri);
        if ($uri === false || !isset($uri['scheme'], $uri['host']) || !\in_array($uri['scheme'], ['http', 'https'], true) || isset($uri['query']) || isset($uri['fragment'])) {
            throw new \InvalidArgumentException('Invalid base URI: expected an HTTP(S) URL without a query string or fragment');
        }

        parent::__construct($level, $channels);

        $this->formatter = self::createFormatter($appName);
    }

    public function start(): Future
    {
        return async(function (): void {
            $this->httpClient = self::createHttpTransport();

            $endpoint = \rtrim($this->baseUri, '/') . '/insert/jsonline?_stream_fields=' . \rawurlencode(\implode(',', $this->streamFields));
            $this->requestTemplate = new Request($endpoint, 'POST');
            $this->requestTemplate->setHeader('content-type', 'application/stream+json');
            $this->requestTemplate->setTcpConnectTimeout(self::REQUEST_TIMEOUT);
            $this->requestTemplate->setTlsHandshakeTimeout(self::REQUEST_TIMEOUT);
            $this->requestTemplate->setTransferTimeout(self::REQUEST_TIMEOUT);
            $this->requestTemplate->setInactivityTimeout(self::REQUEST_TIMEOUT);
        });
    }

    public function handle(LogEntry $record): void
    {
        $line = $this->formatter->format($record) . "\n";
        $lineLength = \strlen($line);
        if ($this->bufferSize + $lineLength > self::MAX_BUFFER_SIZE) {
            return;
        }

        $this->buffer[] = $line;
        $this->bufferSize += $lineLength;

        if ($this->pendingRequest) {
            return;
        }

        $this->pendingRequest = true;

        EventLoop::queue(function (): void {
            while ($this->buffer !== []) {
                $buffer = $this->buffer;
                $bufferSize = $this->bufferSize;
                $this->buffer = [];
                $this->bufferSize = 0;

                try {
                    $request = clone $this->requestTemplate;
                    $request->setBody(StreamedContent::fromStream(new ReadableIterableStream($buffer), $bufferSize));
                    $this->httpClient->request($request);
                } catch (\Throwable) {
                    // ignore
                }
            }

            $this->pendingRequest = false;
        });
    }

    private static function createFormatter(string $appName): Formatter
    {
        return new class ($appName) implements Formatter {
            public function __construct(private readonly string $appName)
            {
            }

            public function format(LogEntry $record): string
            {
                return \json_encode([
                    '_time' => $record->time->format('Y-m-d\TH:i:s.uP'),
                    '_msg' => $record->message,
                    'level' => $record->level->toString(),
                    'channel' => $record->channel,
                    'app' => $this->appName,
                    'pid' => $record->pid,
                    'context' => $this->normalize($record->context),
                ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }

            private function normalize(mixed $value): mixed
            {
                if (\is_array($value)) {
                    foreach ($value as $key => $item) {
                        $value[$key] = $this->normalize($item);
                    }

                    return $value;
                }

                if ($value instanceof FlattenException) {
                    $trace = $value->getFullExceptionTextWithTrace();
                    while (($value = $value->previous) !== null) {
                        $trace .= "\n\nPrevious:\n" . $value->getFullExceptionTextWithTrace();
                    }

                    return $trace;
                }

                if ($value instanceof FlattenDateTime) {
                    return $value->dt->format('Y-m-d\TH:i:s.uP');
                }

                return $value instanceof \Stringable ? (string) $value : $value;
            }
        };
    }

    private static function createHttpTransport(): HttpClient
    {
        if (!\class_exists(HttpClient::class)) {
            throw new \RuntimeException(\sprintf('You cannot use "%s" because the "amphp/http-client" package is not installed. Try running "composer require amphp/http-client"', __CLASS__));
        }

        return (new HttpClientBuilder())
            ->retry(0)
            ->followRedirects(0)
            ->skipAutomaticCompression()
            ->allowDeprecatedUriUserInfo()
            ->build();
    }
}
