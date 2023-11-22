<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console;

interface Command
{
    public function getCommand(): string;

    public function getUsageExample(): string;

    public function run(array $arguments): never;
}