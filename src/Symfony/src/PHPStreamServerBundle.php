<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class PHPStreamServerBundle extends AbstractBundle
{
    protected string $extensionAlias = 'phpstreamserver';

    public function configure(DefinitionConfigurator $definition): void
    {
        $configurator = require __DIR__ . '/config/configuration.php';
        $configurator($definition);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $configurator = require __DIR__ . '/config/services.php';
        $configurator($config, $builder);
    }
}
