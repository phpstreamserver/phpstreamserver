<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

use Revolt\EventLoop;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @internal
 */
final readonly class ConsoleRunner implements RunnerInterface
{
    public function __construct(private AppLoader $appLoader)
    {
    }

    public function run(): int
    {
        \set_time_limit(0);

        $input = new ArgvInput();
        $output = new ConsoleOutput();

        if (null !== $env = $input->getParameterOption(['--env', '-e'], null, true)) {
            $envVarName = $this->appLoader->options['env_var_name'];
            \putenv($envVarName . '=' . $_SERVER[$envVarName] = $_ENV[$envVarName] = $env);
        }

        if ($input->hasParameterOption('--no-debug', true)) {
            $debugVarName = $this->appLoader->options['debug_var_name'];
            \putenv($debugVarName . '=' . $_SERVER[$debugVarName] = $_ENV[$debugVarName] = '0');
        }

        $this->appLoader->loadEnv();
        $app = $this->appLoader->loadApp();

        $application = match (true) {
            $app instanceof Application => $app,
            $app instanceof KernelInterface => new Application($app),
            default => throw new \TypeError(\sprintf('Invalid app value: "%s" or "%s" expected, "%s" returned', Application::class, KernelInterface::class, \get_debug_type($app))),
        };

        $application->setAutoExit(false);

        $ret = 0;

        EventLoop::setErrorHandler(static function (\Throwable $e) use ($application, $output, &$ret): void {
            $application->renderThrowable($e, $output);
            $ret = Command::FAILURE;
        });

        EventLoop::queue(static function () use ($application, $input, $output, &$ret): void {
            $ret = $application->run($input, $output);

            $container = $application->getKernel()->getContainer();
            if ($container->has('monolog.handler.console')) {
                $monologHandler = $container->get('monolog.handler.console');
                $monologHandler->setInput($input);
                $monologHandler->setOutput($output);
            }
        });

        EventLoop::run();

        return $ret;
    }
}
