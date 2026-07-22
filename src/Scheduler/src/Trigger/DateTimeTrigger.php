<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Trigger;

final readonly class DateTimeTrigger implements TriggerInterface
{
    public function __construct(private \DateTimeImmutable $date)
    {
    }

    public function getNextRunDate(\DateTimeImmutable $now): \DateTimeImmutable|null
    {
        return $this->date > $now ? $this->date : null;
    }

    public function __toString(): string
    {
        return $this->date->format('Y-m-d H:i:s P');
    }
}
