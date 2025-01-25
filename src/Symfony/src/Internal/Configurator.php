<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Symfony\Event\ProcessStartEvent;
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

    public function __invoke(ProcessStartEvent $event): void
    {
        $kernelContainer = $this->kernel->getContainer();
        $workerContainer = $event->worker->getContainer();

        $kernelContainer->set('phpss.container', $workerContainer);
        $kernelContainer->set('phpss.logger', $event->worker->getLogger());

        $workerContainer->setService('kernel', $this->kernel);

        /** @var HttpRequestHandler $symfonyHttpRequestHandler */
        $symfonyHttpRequestHandler = $kernelContainer->get('phpss.http_handler');

        $workerContainer->setService('request_handler', static function(Request $request) use ($symfonyHttpRequestHandler): Response {
            return $symfonyHttpRequestHandler($request);
        });

        $workerContainer->setParameter('server_dir', $this->kernel->getProjectDir() . '/public');
    }
}
