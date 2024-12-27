<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Http;

use Luzrain\PHPStreamServer\WorkerProcess;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final readonly class HttpRequestHandler
{
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;
    private HttpMessageFactoryInterface $psrHttpFactory;
    private HttpFoundationFactoryInterface $httpFoundationFactory;

    public function __construct(private KernelInterface $kernel)
    {
        $psr17Factory = new Psr17Factory();
        $this->responseFactory = $psr17Factory;
        $this->streamFactory = $psr17Factory;
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $this->httpFoundationFactory = new HttpFoundationFactory();
    }

    /**
     * @throws \Exception
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        \memory_reset_peak_usage();

        if (null !== $file = $this->findFileInPublicDirectory($request->getUri()->getPath())) {
            return $this->handleFile($file);
        } else {
            return $this->handle($request);
        }
    }

    private function handleFile(string $file): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withBody($this->streamFactory->createStreamFromFile($file))
            ->withHeader('Content-Type', (new MimeTypeMapper())->lookupMimeTypeFromPath($file))
        ;
    }

    /**
     * @throws \Exception
     */
    private function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->kernel->boot();
        $symfonyRequest = $this->httpFoundationFactory->createRequest($request);
        $symfonyResponse = $this->kernel->handle($symfonyRequest);

        /** @var WorkerProcess $worker */
        $worker = $this->kernel->getContainer()->get('phpstreamserver.worker');

        $worker->getEventLoop()->defer(function () use ($symfonyRequest, $symfonyResponse): void {
            if ($this->kernel instanceof TerminableInterface) {
                $this->kernel->terminate($symfonyRequest, $symfonyResponse);
            }
        });

        return $this->psrHttpFactory->createResponse($symfonyResponse);
    }

    private function findFileInPublicDirectory(string $requestPath): string|null
    {
        $publicDir = $this->kernel->getProjectDir() . '/public';
        $path = \realpath($publicDir . $requestPath);

        if ($path === false || !\file_exists($path) || \is_dir($path) || !\str_starts_with($path, $publicDir . '/')) {
            return null;
        }

        return $path;
    }
}
