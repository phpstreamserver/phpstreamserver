<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Trigger;

use Cron\CronExpression;

final readonly class CronExpressionTrigger implements TriggerInterface
{
    private CronExpression $expression;

    public function __construct(string $expression)
    {
        try {
            $this->expression = new CronExpression($expression);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\sprintf('Invalid cron expression "%s"', $expression), 0, $e);
        }
    }

    public function __toString(): string
    {
        return $this->expression->getExpression() ?? '???';
    }

    public function getNextRunDate(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($this->expression->getNextRunDate($now));
    }
}
