<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Trigger;

final class JitterTrigger implements TriggerInterface
{
    public function __construct(private readonly TriggerInterface $trigger, private readonly int $jitter)
    {
    }

    public function __toString(): string
    {
        return \sprintf('%s with 0-%d seconds of jitter', $this->trigger, $this->jitter);
    }

    public function getNextRunDate(\DateTimeImmutable $now): \DateTimeImmutable|null
    {
        /** @var \DateTimeImmutable|null */
        return $this->trigger->getNextRunDate($now)?->modify(\sprintf('+%d seconds', \random_int(0, $this->jitter)));
    }
}
