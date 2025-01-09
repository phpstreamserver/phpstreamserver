<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use PHPStreamServer\Core\Worker\LoggerInterface;
use PHPStreamServer\Plugin\HttpServer\HttpServerProcess;
use PHPStreamServer\Symfony\Event\HttpServerStartedEvent;
use PHPStreamServer\Symfony\Http\HttpRequestHandler;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final readonly class Configurator
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function __invoke(HttpServerStartedEvent $event): void
    {
        $kernelContainer = $this->kernel->getContainer();
        $workerContainer = $event->worker->container;

        $kernelContainer->set('phpss.container', $workerContainer);
        $kernelContainer->set('phpss.bus', $event->worker->bus);
        $kernelContainer->set('phpss.logger', $event->worker->logger);

        $workerContainer->setParameter('server_dir', $this->kernel->getProjectDir() . '/public');

        /** @var HttpRequestHandler $symfonyHttpRequestHandler */
        $symfonyHttpRequestHandler = $kernelContainer->get('phpss.http_handler');

        $workerContainer->setService('request_handler', static function(Request $request) use ($symfonyHttpRequestHandler): Response {
            return $symfonyHttpRequestHandler($request);
        });




//        /**
//         * @psalm-suppress UndefinedClass
//         * @psalm-suppress UndefinedInterfaceMethod
//         */
//        if ($this->logger instanceof \Monolog\Logger) {
//            $this->logger = $this->logger->withName('phpstreamserver');
//        }
//        $errorHandler = ErrorHandler::register(null, false);
//        $errorHandlerClosure = static function (\Throwable $e) use ($errorHandler): void {
//            $errorHandler->setExceptionHandler(static function (\Throwable $e): void {});
//            /** @psalm-suppress InternalMethod */
//            $errorHandler->handleException($e);
//        };
//
//        $worker->setLogger($this->logger);
//        $worker->setErrorHandler($errorHandlerClosure);
    }
}
