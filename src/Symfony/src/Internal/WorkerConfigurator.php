<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Internal;

use Luzrain\PHPStreamServer\WorkerProcess;
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\KernelInterface;

final class WorkerConfigurator
{
    public function __construct(private KernelInterface $kernel, private LoggerInterface $logger)
    {
    }

    public function configure(WorkerProcess $worker): void
    {
        /**
         * @psalm-suppress UndefinedClass
         * @psalm-suppress UndefinedInterfaceMethod
         */
        if ($this->logger instanceof \Monolog\Logger) {
            $this->logger = $this->logger->withName('phpstreamserver');
        }

        $errorHandler = ErrorHandler::register(null, false);
        $errorHandlerClosure = static function (\Throwable $e) use ($errorHandler): void {
            $errorHandler->setExceptionHandler(static function (\Throwable $e): void {});
            /** @psalm-suppress InternalMethod */
            $errorHandler->handleException($e);
        };

        $worker->setLogger($this->logger);
        $worker->setErrorHandler($errorHandlerClosure);

        $this->kernel->getContainer()->set('phpstreamserver.worker', $worker);
    }
}
