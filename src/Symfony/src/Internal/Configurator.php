<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Plugin\HttpServer\HttpServerProcess;
use PHPStreamServer\Symfony\Event\HttpServerStartedEvent;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class Configurator
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function __invoke(HttpServerStartedEvent $event): void
    {
        $worker = $event->worker;

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


        $kernelContainer = $this->kernel->getContainer();
        $kernelContainer->set('phpss.worker', $worker);
        $worker->container->setService('http_handler', $kernelContainer->get('phpss.http_handler'));
    }
}
