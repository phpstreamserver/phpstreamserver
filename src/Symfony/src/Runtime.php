<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use PHPStreamServer\Symfony\Internal\AppLoader;
use PHPStreamServer\Symfony\Internal\Runner;
use Symfony\Component\Runtime\ResolverInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\RuntimeInterface;

final class Runtime implements RuntimeInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $options['env_var_name'] ??= 'APP_ENV';
        $options['debug_var_name'] ??= 'APP_DEBUG';
        $this->options = $options;
    }

    public function getRunner(object|null $application): RunnerInterface
    {
        if (!$application instanceof AppLoader) {
            throw new \LogicException(\sprintf('Not supported application type, %s was expected', AppLoader::class));
        }

        return new Runner($application);
    }

    public function getResolver(callable $callable, \ReflectionFunction|null $reflector = null): ResolverInterface
    {
        $callable = $callable(...);

        return new class ($callable, $this->options) implements ResolverInterface {
            public function __construct(private readonly \Closure $callable, private readonly array $options)
            {
            }

            public function resolve(): array
            {
                return [static fn(mixed ...$args) => new AppLoader(...$args), [$this->callable, $this->options]];
            }
        };
    }
}
