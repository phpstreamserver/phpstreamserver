<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\ReloadStrategy;

use Luzrain\PHPStreamServer\Exception\HttpException;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PHPStreamServerBundle\Event\HttpServerStartEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class OnException implements ReloadStrategyInterface
{
    private array $allowedExceptions = [
        HttpException::class,
        HttpExceptionInterface::class,
        '\Symfony\Component\Serializer\Exception\ExceptionInterface',
    ];

    private bool $scheduleReload = false;

    /**
     * @param list<class-string<\Throwable>> $allowedExceptions
     */
    public function __construct(array $allowedExceptions = [])
    {
        \array_push($this->allowedExceptions, ...$allowedExceptions);
    }

    public function onServerStart(HttpServerStartEvent $event): void
    {
        $event->worker->addReloadStrategies($this);
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->getRequestType() === HttpKernelInterface::MAIN_REQUEST) {
            $this->scheduleReload = $this->shouldExceptionTriggerReload($event->getThrowable());
        }
    }

    public function shouldReload(int $eventCode, mixed $eventObject = null): bool
    {
        if ($eventCode === self::EVENT_CODE_EXCEPTION && $eventObject instanceof \Throwable) {
            return $this->shouldExceptionTriggerReload($eventObject);
        }

        if ($eventCode === self::EVENT_CODE_REQUEST) {
            return $this->scheduleReload;
        }

        return false;
    }

    private function shouldExceptionTriggerReload(\Throwable $e): bool
    {
        foreach ($this->allowedExceptions as $allowedExceptionClass) {
            if ($e instanceof $allowedExceptionClass) {
                return false;
            }
        }

        return true;
    }
}
