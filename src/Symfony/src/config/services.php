<?php

declare(strict_types=1);

use PHPStreamServer\Core\Logger\LoggerInterface;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Worker\ContainerInterface;
use PHPStreamServer\Symfony\Event\HttpServerStartEvent;
use PHPStreamServer\Symfony\Http\DeleteUploadedFilesListener;
use PHPStreamServer\Symfony\Http\HttpRequestHandler;
use PHPStreamServer\Symfony\Internal\Configurator;
use PHPStreamServer\Symfony\Internal\ExceptionListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (array $config, ContainerConfigurator $container) {
    $services = $container->services();

    $services
        ->set('phpss.http_handler', HttpRequestHandler::class)
        ->args([service('kernel')])
        ->public()
    ;

    $services
        ->set('phpss.configurator', Configurator::class)
        ->args([service('kernel')])
        ->tag('kernel.event_listener', [
            'event' => HttpServerStartEvent::class,
            'priority' => 8192,
        ])
    ;

    $services
        ->set('phpss.exception_listener', ExceptionListener::class)
        ->args([service('phpss.container')])
        ->tag('kernel.event_listener', [
            'event' => ExceptionEvent::class,
            'method' => 'onException',
            'priority' => -100,
        ])
        ->tag('kernel.event_listener', [
            'event' => TerminateEvent::class,
            'method' => 'onTerminate',
            'priority' => -2049,
        ])
    ;

    $services
        ->set('phpss.container', ContainerInterface::class)
        ->synthetic()
    ;

    $services
        ->set('phpss.bus', MessageBusInterface::class)
        ->synthetic()
    ;

    $services
        ->set('phpss.logger', LoggerInterface::class)
        ->synthetic()
    ;

    $services->alias(ContainerInterface::class, 'phpss.container');
    $services->alias(MessageBusInterface::class, 'phpss.bus');
    $services->alias(LoggerInterface::class, 'phpss.logger');

    $services
        ->set('phpss.delete_uploaded_files_listener', DeleteUploadedFilesListener::class)
        ->tag('kernel.event_listener', [
            'event' => TerminateEvent::class,
            'priority' => -2048,
        ])
    ;
};
