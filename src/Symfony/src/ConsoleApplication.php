<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class ConsoleApplication
{
    /**
     * @template T of KernelInterface|Application
     * @param \Closure(mixed...): T $app
     */
    public function __construct(public \Closure $app)
    {
    }

    public function __invoke(): self
    {
        return $this;
    }
}
