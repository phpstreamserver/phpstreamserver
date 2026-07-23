<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler;

final class WorkerInfo
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RUNNING = 'running';
    public const STATUS_CANCEL = 'cancel';

    /**
     * @param self::STATUS_* $status
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $user,
        public readonly string $schedule,
        public string $status,
        public \DateTimeInterface $nextRunDateTime,
    ) {
    }
}
