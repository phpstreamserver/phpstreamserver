<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Command;

use PHPStreamServer\Core\Console\Command;
use PHPStreamServer\Core\Console\Table;
use PHPStreamServer\Core\MessageBus\ExternalProcessMessageBus;
use PHPStreamServer\Plugin\Scheduler\Message\GetSchedulerStatusCommand;
use PHPStreamServer\Plugin\Scheduler\Status\PeriodicWorkerInfo;
use PHPStreamServer\Plugin\Scheduler\Status\SchedulerStatus;

/**
 * @internal
 */
final class SchedulerCommand extends Command
{
    public const COMMAND = 'scheduler';
    public const DESCRIPTION = 'Show scheduler map';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $bus = new ExternalProcessMessageBus($args['pidFile'], $args['socketFile']);

        echo "❯ Scheduler\n";

        $status = $bus->dispatch(new GetSchedulerStatusCommand())->await();
        \assert($status instanceof SchedulerStatus);

        if ($status->getPeriodicTasksCount() > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Schedule',
                    'Next run',
                    'Status',
                ])
                ->addRows(\array_map(array: $status->getPeriodicWorkers(), callback: static fn(PeriodicWorkerInfo $w) => [
                    $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                    $w->name,
                    $w->schedule ?: '-',
                    $w->nextRunDate?->format('Y-m-d H:i:s') ?? '<color;fg=gray>-</>',
                    match (true) {
                        $w->nextRunDate !== null => '[<color;fg=green>OK</>]',
                        default => '[<color;fg=red>ERROR</>]',
                    },
                ]));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no scheduled tasks</>\n";
        }

        return 0;
    }
}
