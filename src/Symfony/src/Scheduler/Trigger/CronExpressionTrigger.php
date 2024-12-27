<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServerBundle\Scheduler\Trigger;

use Cron\CronExpression;

/**
 * @psalm-suppress UndefinedClass
 */
final class CronExpressionTrigger implements TriggerInterface
{
    private CronExpression $expression;

    public function __construct(string $expression)
    {
        if (!\class_exists(CronExpression::class)) {
            throw new \LogicException(\sprintf('You cannot use "%s" as the "cron expression" package is not installed. Try running "composer require dragonmantank/cron-expression".', __CLASS__));
        }

        try {
            $this->expression = new CronExpression($expression);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\sprintf('Invalid cron expression "%s"', $expression), 0, $e);
        }
    }

    public function __toString(): string
    {
        return $this->expression->getExpression();
    }

    public function getNextRunDate(\DateTimeImmutable $now): \DateTimeImmutable|null
    {
        $next = $this->expression->getNextRunDate($now);
        return $next instanceof \DateTime ? \DateTimeImmutable::createFromMutable($next) : $next;
    }
}
