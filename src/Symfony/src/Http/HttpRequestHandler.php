<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final readonly class HttpRequestHandler
{
    private HttpFoundationFactory $httpFoundationFactory;
    private AmpHttpFactory $ampHttpFactory;

    public function __construct(private KernelInterface $kernel)
    {
        $this->httpFoundationFactory = new HttpFoundationFactory();
        $this->ampHttpFactory = new AmpHttpFactory();
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request): Response
    {
        $kernel = $this->kernel;
        $kernel->boot();

        $symfonyRequest = $this->httpFoundationFactory->createRequest($request);
        $symfonyResponse = $kernel->handle($symfonyRequest);
        $response = $this->ampHttpFactory->createResponse($symfonyResponse);

        if ($kernel instanceof TerminableInterface) {
            $response->onDispose(static function () use ($kernel, $symfonyRequest, $symfonyResponse): void {
                $kernel->terminate($symfonyRequest, $symfonyResponse);
            });
        }

        return $response;
    }
}
