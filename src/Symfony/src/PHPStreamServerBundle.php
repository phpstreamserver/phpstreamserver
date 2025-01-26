<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use PHPStreamServer\Core\Logger\NullLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class PHPStreamServerBundle extends AbstractBundle implements CompilerPassInterface
{
    protected string $extensionAlias = 'phpstreamserver';

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $configurator = require __DIR__ . '/config/services.php';
        $configurator($config, $container);
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('logger')) {
            $container->setAlias('logger', 'phpss.logger');
        }

        if (!$container->has('monolog.logger')) {
            $container->removeDefinition('phpss.monolog_handler');
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(pass: $this, priority: -28);
    }

    public function boot(): void
    {
        /** @psalm-suppress PossiblyNullReference */
        $this->container->set('phpss.logger', new NullLogger());
    }
}
