<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Command;

use PHPStreamServer\Core\Command\StartCommand as BaseStartCommand;
use PHPStreamServer\Symfony\Internal\AppLoader;

final class StartCommand extends BaseStartCommand
{
    public function __construct(private AppLoader $appLoader)
    {
    }

    public function configure(): void
    {
        parent::configure();
        $this->options->addOptionDefinition('env', 'e', 'The environment name');
        $this->options->addOptionDefinition('no-debug', null, 'Switch off debug mode');
    }

    public function execute(array $args): int
    {
        $env = $this->options->getOption('env');
        $noDebug = (bool) $this->options->getOption('no-debug');

        if (\is_string($env) && $env !== '') {
            $envVarName = $this->appLoader->options['env_var_name'];
            \putenv($envVarName . '=' . $_SERVER[$envVarName] = $_ENV[$envVarName] = $env);
        }

        if ($noDebug) {
            $debugVarName = $this->appLoader->options['debug_var_name'];
            \putenv($debugVarName . '=' . $_SERVER[$debugVarName] = $_ENV[$debugVarName] = '0');
        }

        $this->appLoader->loadEnv();

        return parent::execute($args);
    }
}
