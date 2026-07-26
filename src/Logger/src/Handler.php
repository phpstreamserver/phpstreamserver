<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

use Amp\Future;

interface Handler
{
    public function start(): Future;

    public function isHandling(LogEntry $record): bool;

    public function handle(LogEntry $record): void;
}
