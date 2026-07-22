<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Trigger;

final readonly class TriggerFactory
{
    private function __construct()
    {
    }

    /**
     * Creates a trigger from a schedule expression. Supported formats include:
     *
     *  * An integer or string representing the frequency in seconds;
     *  * An ISO 8601 date-time format;
     *  * An ISO 8601 duration format;
     *  * A relative date format supported by \DateInterval;
     *  * A \DateInterval instance;
     *  * A \DateTimeImmutable instance;
     *  * A cron expression.
     *
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations
     * @see https://www.php.net/manual/en/dateinterval.createfromdatestring.php
     * @see https://github.com/dragonmantank/cron-expression
     *
     * @throws \InvalidArgumentException
     */
    public static function create(string|int|\DateInterval|\DateTimeImmutable $expression, int $jitter = 0): TriggerInterface
    {
        if (\is_string($expression) && false !== $iso8601DateTime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $expression)) {
            $expression = $iso8601DateTime;
        }

        $trigger = match (true) {
            $expression instanceof \DateTimeImmutable => new DateTimeTrigger($expression),
            \is_string($expression) && self::isCronExpression($expression) => new CronExpressionTrigger($expression),
            default => new PeriodicTrigger($expression),
        };

        return $jitter > 0 ? new JitterTrigger($trigger, $jitter) : $trigger;
    }

    private static function isCronExpression(string $expression): bool
    {
        return \preg_match('~\A(?:@(?:yearly|annually|monthly|weekly|daily|midnight|hourly)|(?:[\d*/,-]+\h+){4}[\d*/,-]+)\z~', $expression) === 1;
    }
}
