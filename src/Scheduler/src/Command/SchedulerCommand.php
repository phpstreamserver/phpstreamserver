<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Command;

use PHPStreamServer\Core\Console\Command;
use PHPStreamServer\Core\Console\Table;
use PHPStreamServer\Core\MessageBus\ExternalProcessMessageBus;
use PHPStreamServer\Plugin\Scheduler\Message\GetWorkersCommand;
use PHPStreamServer\Plugin\Scheduler\WorkerInfo;

/**
 * @internal
 */
final class SchedulerCommand extends Command
{
    public static function getName(): string
    {
        return 'scheduler';
    }

    public static function getDescription(): string
    {
        return 'Show scheduler status';
    }

    public function execute(string $pidFile, string $socketFile): int
    {
        $bus = new ExternalProcessMessageBus($pidFile, $socketFile);

        echo "❯ Scheduler\n";

        $workers = $bus->dispatch(new GetWorkersCommand())->await();

        if (\count($workers) > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Schedule',
                    'Next run',
                    'Status',
                ])
                ->addRows(\array_map(array: $workers, callback: static fn(WorkerInfo $w): array => [
                    $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                    $w->name,
                    $w->schedule ?: '-',
                    $w->nextRunDateTime->format(\DateTimeInterface::RFC7231),
                    match ($w->status) {
                        WorkerInfo::STATUS_SCHEDULED => '[<color;fg=green>SCHEDULED</>]',
                        default => '[<color;fg=green>RUNNING</>]',
                    },
                ]));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no scheduled tasks</>\n";
        }

        return 0;
    }
}
