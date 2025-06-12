<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\MessageBus\ExternalProcessMessageBus;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;

/**
 * @internal
 */
final readonly class MessageBusFactory
{
    public function __construct(
        private string $pidFile,
        private string $socketFile,
        private ContainerInterface|null $workerContainer,
    ) {
    }

    public function create(): MessageBusInterface
    {
        if ($this->workerContainer instanceof ContainerInterface) {
            return $this->workerContainer->getService(MessageBusInterface::class);
        }

        return new ExternalProcessMessageBus($this->pidFile, $this->socketFile);
    }
}
