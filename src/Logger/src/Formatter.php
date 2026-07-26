<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

interface Formatter
{
    public function format(LogEntry $record): string;
}
