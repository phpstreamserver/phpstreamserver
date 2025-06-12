<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use Symfony\Component\HttpKernel\KernelInterface;

final readonly class ServerApplication
{
    /**
     * @param \Closure(): KernelInterface $kernelFactory
     */
    public function __construct(public \Closure $kernelFactory)
    {
    }

    public function __invoke(): self
    {
        return $this;
    }
}
