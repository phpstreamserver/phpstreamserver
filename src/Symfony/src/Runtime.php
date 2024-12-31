<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use Symfony\Component\Runtime\ResolverInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

class Runtime extends SymfonyRuntime
{
    public function getRunner(object|null $application): RunnerInterface
    {
        if ($application instanceof KernelFactory) {
            return new Runner($application);
        }

        return parent::getRunner($application);
    }

    public function getResolver(callable $callable, \ReflectionFunction|null $reflector = null): ResolverInterface
    {
        $resolver = parent::getResolver($callable, $reflector);

        return new class ($resolver, $this->options) implements ResolverInterface {
            public function __construct(private ResolverInterface $resolver, private array $options)
            {
            }

            public function resolve(): array
            {
                [$app, $args] = $this->resolver->resolve();

                return [static fn(mixed ...$args) => new KernelFactory(...$args), [$app, $args, $this->options]];
            }
        };
    }
}
