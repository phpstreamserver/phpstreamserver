<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use PHPStreamServer\Symfony\Internal\KernelLoader;
use PHPStreamServer\Symfony\Internal\Runner;
use Symfony\Component\Runtime\ResolverInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

final class Runtime extends SymfonyRuntime
{
    public function getRunner(object|null $application): RunnerInterface
    {
        if ($application instanceof KernelLoader) {
            return new Runner($application);
        }

        return parent::getRunner($application);
    }

    public function getResolver(callable $callable, \ReflectionFunction|null $reflector = null): ResolverInterface
    {
        $resolver = parent::getResolver($callable, $reflector);

        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            return $resolver;
        }

        return new class ($resolver, $this->options) implements ResolverInterface {
            public function __construct(private readonly ResolverInterface $resolver, private readonly array $options)
            {
            }

            public function resolve(): array
            {
                [$app, $args] = $this->resolver->resolve();

                return [static fn(mixed ...$args) => new KernelLoader(...$args), [$app, $args, $this->options]];
            }
        };
    }
}
