<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

use PHPStreamServer\Plugin\Logger\Internal\LogEntry;

abstract class AbstractHandler implements Handler
{
    private readonly LogLevel $level;

    /** @var array<string> */
    private array $includeChannels = [];

    /** @var array<string> */
    private array $excludeChannels = [];

    public function __construct(LogLevel $level = LogLevel::DEBUG, array $channels = [])
    {
        $this->level = $level;

        foreach ($channels as $channel) {
            if (\str_starts_with($channel, '!')) {
                $this->excludeChannels[] = \substr($channel, 1);
            } else {
                $this->includeChannels[] = $channel;
            }
        }
    }

    public function isHandling(LogEntry $record): bool
    {
        if ($record->level->value < $this->level->value) {
            return false;
        }

        if ($this->includeChannels !== [] && !\in_array($record->channel, $this->includeChannels, true)) {
            return false;
        }

        if ($this->excludeChannels !== [] && \in_array($record->channel, $this->excludeChannels, true)) {
            return false;
        }

        return true;
    }
}
