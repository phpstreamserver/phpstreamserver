<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal\Http;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Symfony\SymfonyEvent\WorkerStartEvent;
use PHPStreamServer\Symfony\Worker\SymfonyHttpServerWorker;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @internal
 */
final readonly class HttpRequestHandler
{
    private HttpFoundationFactory $httpFoundationFactory;
    private AmpHttpFactory $ampHttpFactory;

    /** @var \WeakMap<ReadableStream, true> */
    private \WeakMap $streamResponses;

    public function __construct(private KernelInterface $kernel)
    {
        $this->httpFoundationFactory = new HttpFoundationFactory();
        $this->ampHttpFactory = new AmpHttpFactory();

        /** @var \WeakMap<ReadableStream, true> */
        $this->streamResponses = new \WeakMap();
    }

    public function onWorkerStart(WorkerStartEvent $event): void
    {
        if (!$event->worker instanceof SymfonyHttpServerWorker) {
            return;
        }

        $streams = $this->streamResponses;
        $closeConnections = static function () use ($streams): void {
            foreach ($streams as $stream => $_) {
                $stream->close();
            }
        };

        $event->worker->onStop($closeConnections, priority: -1010);
        $event->worker->onReload($closeConnections, priority: -1010);
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request): Response
    {
        $kernel = $this->kernel;
        $kernel->boot();

        $httpFoundationFactory = $this->httpFoundationFactory;
        $symfonyRequest = $this->httpFoundationFactory->createRequest($request);
        $symfonyResponse = $kernel->handle($symfonyRequest);
        $response = $this->ampHttpFactory->createResponse($symfonyResponse);

        if ($symfonyResponse instanceof StreamedResponse) {
            $body = $response->getBody();
            $this->streamResponses->offsetSet($body, true);
            $bodyReference = \WeakReference::create($body);
            $client = $request->getClient();
            $client->onClose(static function () use ($bodyReference, $client): void {
                $bodyReference->get()?->close();
                $client->close();
            });
        }

        if ($kernel instanceof TerminableInterface) {
            $response->onDispose(static function () use ($kernel, $httpFoundationFactory, $request, $symfonyRequest, $symfonyResponse): void {
                $kernel->terminate($symfonyRequest, $symfonyResponse);
                $httpFoundationFactory->disposeTempArtifacts($request);
            });
        }

        return $response;
    }
}
