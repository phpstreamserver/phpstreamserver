<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Plugin\Scheduler\Trigger\TriggerFactory;
use PHPStreamServer\Test\data\PHPSSTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TriggerFactoryTest extends PHPSSTestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-01-01 00:00:00');
    }

    public static function expressions(): \Generator
    {
        yield [10, '2026-01-01 00:00:10'];
        yield ['10', '2026-01-01 00:00:10'];
        yield ['2026-01-01T00:00:10Z', '2026-01-01 00:00:10'];
        yield ['PT10S', '2026-01-01 00:00:10'];
        yield ['10 seconds', '2026-01-01 00:00:10'];
        yield ['*/5 * * * *', '2026-01-01 00:05:00'];
        yield ['@daily', '2026-01-02 00:00:00'];
    }

    #[DataProvider('expressions')]
    public function testExpressions(string|int $expression, string $expectedNextRun): void
    {
        // Arrange
        $trigger = TriggerFactory::create($expression);
        $nextRun = $trigger->getNextRunDate($this->now);

        // Assert
        $this->assertEquals($expectedNextRun, $nextRun?->format('Y-m-d H:i:s'));
    }

    public function testDateInterval(): void
    {
        // Arrange
        $trigger = TriggerFactory::create(\DateInterval::createFromDateString('10 seconds'));
        $nextRun = $trigger->getNextRunDate($this->now);

        // Assert
        $this->assertEquals('2026-01-01 00:00:10', $nextRun?->format('Y-m-d H:i:s'));
    }

    public function testDateTimeImmutable(): void
    {
        // Arrange
        $trigger = TriggerFactory::create(new \DateTimeImmutable('2026-01-01 00:00:10'));
        $nextRun = $trigger->getNextRunDate($this->now);

        // Assert
        $this->assertEquals('2026-01-01 00:00:10', $nextRun?->format('Y-m-d H:i:s'));
    }
}
