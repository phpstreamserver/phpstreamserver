<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use PHPStreamServer\Core\ContainerInterface;
use PHPStreamServer\Core\MessageBus\ExternalProcessMessageBus;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;

final class MessageBusFactory
{
    public function __construct(private bool $isPhpSSRuntimeLoaded, private string $phpSSCacheFile, private ContainerInterface|null $workerContainer)
    {
    }

    public function create(): MessageBusInterface
    {
        if ($this->isPhpSSRuntimeLoaded && $this->workerContainer instanceof ContainerInterface) {
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
