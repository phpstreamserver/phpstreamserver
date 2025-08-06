<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use Symfony\Component\HttpKernel\KernelInterface;

final readonly class ServerApplication
{
    /**
     * @template T of KernelInterface
     * @param \Closure(mixed ...$args): T $app
     */
    public function __construct(public \Closure $app)
    {
    }

    public function __invoke(): self
    {
        return $this;
    }
}
