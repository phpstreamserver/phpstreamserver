<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Scheduler\Trigger;

final class DateTimeTrigger implements TriggerInterface
{
    private \DateTimeImmutable $date;

    public function __construct(string|\DateTimeImmutable $date)
    {
        if (\is_string($date) && $iso8601Date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $date)) {
            $date = $iso8601Date;
        }

        if (\is_string($date)) {
            try {
                $this->date = new \DateTimeImmutable($date);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(\sprintf('Invalid date string "%s": %s', $date, $e->getMessage()), 0, $e);
            }
        } else {
            $this->date = $date;
        }
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
