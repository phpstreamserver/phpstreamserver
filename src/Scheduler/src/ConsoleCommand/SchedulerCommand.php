<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\ConsoleCommand;

use PHPStreamServer\Core\Console\Command;
use PHPStreamServer\Core\Console\CommandContext;
use PHPStreamServer\Core\Console\Options;
use PHPStreamServer\Core\Console\Table;
use PHPStreamServer\Core\MessageBus\ExternalProcessMessageBus;
use PHPStreamServer\Plugin\Scheduler\Command\GetWorkersCommand;
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
        return 'List scheduled tasks';
    }

    public function execute(CommandContext $context, Options $options): int
    {
        $bus = new ExternalProcessMessageBus($context->pidFile, $context->socketFile);

        $workers = $bus->dispatch(new GetWorkersCommand())->await();

        echo "<color;fg=brand;options=bold>❯ Scheduler</>\n";

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
                    $w->nextRunDateTime->format('Y-m-d H:i:s T'),
                    match ($w->status) {
                        WorkerInfo::STATUS_SCHEDULED => '<color;fg=green>●</> SCHEDULED',
                        WorkerInfo::STATUS_CANCELLING => '<color;fg=yellow>●</> CANCELLING',
                        default => '<color;fg=green>●</> RUNNING',
                    },
                ]));
        } else {
            echo "  No scheduled tasks configured\n";
        }

        return 0;
    }
}
