<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\MessageBus\ExternalProcessMessageBus;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;

/**
 * @internal
 */
final class MessageBusFactory
{
    public function __construct(private string $phpSSCacheFile, private ContainerInterface|null $workerContainer)
    {
    }

    public function create(): MessageBusInterface
    {
        if ($this->workerContainer instanceof ContainerInterface) {
            return $this->workerContainer->getService(MessageBusInterface::class);
        }

        if (\file_exists($this->phpSSCacheFile)) {
            [$pidFile, $socketFile] = require $this->phpSSCacheFile;
        } else {
            [$pidFile, $socketFile] = ['', ''];
        }

        return new ExternalProcessMessageBus($pidFile, $socketFile);
    }
}
