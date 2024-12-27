<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Worker;

use Luzrain\PHPStreamServer\Listener;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;
use Luzrain\PHPStreamServer\Server\Protocols\Http;
use Luzrain\PHPStreamServer\WorkerProcess;
use Luzrain\PHPStreamServerBundle\Event\HttpServerStartEvent;
use Luzrain\PHPStreamServerBundle\KernelFactory;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class HttpServerWorker extends WorkerProcess
{
    private string $listen;
    private bool $tls;

    public function __construct(
        private readonly KernelFactory $kernelFactory,
        string $listen,
        private readonly string|null $localCert,
        private readonly string|null $localPk,
        string $name,
        int $count,
        string|null $user,
        string|null $group,
        private readonly int $maxBodySize,
    ) {
        if (!\str_starts_with($listen, 'http://') && !\str_starts_with($listen, 'https://')) {
            throw new \InvalidArgumentException('HttpServerWorker only supports http:// and https:// listen');
        }

        $this->tls = \str_starts_with($listen, 'https://');
        $this->listen = \str_replace(['http://', 'https://'], 'tcp://', $listen);

        parent::__construct(
            name: $name,
            count: $count,
            user: $user,
            group: $group,
            onStart: $this->onStart(...),
        );
    }

    private function onStart(): void
    {
        $kernel = $this->kernelFactory->createKernel();
        $kernel->boot();
        $kernel->getContainer()->get('phpstreamserver.worker_configurator')->configure($this);

        /** @var \Closure $httpHandler */
        $httpHandler = $kernel->getContainer()->get('phpstreamserver.http_request_handler');

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $kernel->getContainer()->get('event_dispatcher');

        $this->startListener(new Listener(
            listen: $this->listen,
            protocol: new Http(
                maxBodySize: $this->maxBodySize,
            ),
            tls: $this->tls,
            tlsCertificate: $this->localCert ?? '',
            tlsCertificateKey: $this->localPk ?? '',
            onMessage: function (ConnectionInterface $connection, ServerRequestInterface $data) use ($httpHandler) {
                $connection->send($httpHandler($data));
            },
        ));

        $eventDispatcher->dispatch(new HttpServerStartEvent($this));
    }
}
