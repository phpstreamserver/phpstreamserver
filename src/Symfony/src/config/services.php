<?php

declare(strict_types=1);

use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Worker\ContainerInterface;
use PHPStreamServer\Core\Worker\LoggerInterface;
use PHPStreamServer\Symfony\Event\HttpServerStartedEvent;
use PHPStreamServer\Symfony\Http\HttpRequestHandler;
use PHPStreamServer\Symfony\Internal\Configurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
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
            'event' => HttpServerStartedEvent::class,
            'priority' => 8192,
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

//
//    $container
//        ->register('phpstreamserver.delete_uploaded_files_listener', DeleteUploadedFilesListener::class)
//        ->addTag('kernel.event_listener', [
//            'event' => TerminateEvent::class,
//            'priority' => -1024,
//        ])
//    ;
//
//    if ($config['reload_strategy']['on_exception']['active']) {
//        $container
//            ->register('phpstreamserver.on_exception_reload_strategy', OnException::class)
//            ->addTag('kernel.event_listener', [
//                'event' => HttpServerStartEvent::class,
//                'method' => 'onServerStart',
//            ])
//            ->addTag('kernel.event_listener', [
//                'event' => ExceptionEvent::class,
//                'method' => 'onException',
//                'priority' => -100,
//            ])
//            ->setArguments([
//                $config['reload_strategy']['on_exception']['allowed_exceptions'],
//            ])
//        ;
//    }
//
//    if ($config['reload_strategy']['on_each_request']['active']) {
//        $container
//            ->register('phpstreamserver.on_each_request_reload_strategy', OnEachRequest::class)
//            ->addTag('kernel.event_listener', [
//                'event' => HttpServerStartEvent::class,
//                'method' => 'onServerStart',
//            ])
//        ;
//    }
//
//    if ($config['reload_strategy']['on_ttl_limit']['active']) {
//        $container
//            ->register('phpstreamserver.on_ttl_limit_reload_strategy', OnTTLLimit::class)
//            ->addTag('kernel.event_listener', [
//                'event' => HttpServerStartEvent::class,
//                'method' => 'onServerStart',
//            ])
//            ->setArguments([
//                $config['reload_strategy']['on_ttl_limit']['ttl'],
//            ])
//        ;
//    }
//
//    if ($config['reload_strategy']['on_requests_limit']['active']) {
//        $container
//            ->register('phpstreamserver.on_requests_limit_reload_strategy', OnRequestsLimit::class)
//            ->addTag('kernel.event_listener', [
//                'event' => HttpServerStartEvent::class,
//                'method' => 'onServerStart',
//            ])
//            ->setArguments([
//                $config['reload_strategy']['on_requests_limit']['requests'],
//                $config['reload_strategy']['on_requests_limit']['dispersion'],
//            ])
//        ;
//    }
//
//    if ($config['reload_strategy']['on_memory_limit']['active']) {
//        $container
//            ->register('phpstreamserver.on_on_memory_limit_reload_strategy', OnMemoryLimit::class)
//            ->addTag('kernel.event_listener', [
//                'event' => HttpServerStartEvent::class,
//                'method' => 'onServerStart',
//            ])
//            ->setArguments([
//                $config['reload_strategy']['on_memory_limit']['memory'],
//            ])
//        ;
//    }
};
