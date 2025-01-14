<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Symfony\Event\HttpServerStartEvent;
use PHPStreamServer\Symfony\Http\HttpRequestHandler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final readonly class Configurator
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function __invoke(HttpServerStartEvent $event): void
    {
        $kernelContainer = $this->kernel->getContainer();
        $workerContainer = $event->worker->container;

        $kernelContainer->set('phpss.container', $workerContainer);
        $kernelContainer->set('phpss.logger', $event->worker->logger);

        /** @var HttpRequestHandler $symfonyHttpRequestHandler */
        $symfonyHttpRequestHandler = $kernelContainer->get('phpss.http_handler');

        $workerContainer->setService('request_handler', static function(Request $request) use ($symfonyHttpRequestHandler): Response {
            return $symfonyHttpRequestHandler($request);
        });

        $workerContainer->setService('kernel', $this->kernel);
        $workerContainer->setParameter('server_dir', $this->kernel->getProjectDir() . '/public');
    }
}
