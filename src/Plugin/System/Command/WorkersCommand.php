<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\Message\ContainerGetCommand;
use Luzrain\PHPStreamServer\Plugin\Supervisor\Status\SupervisorStatus;
use Luzrain\PHPStreamServer\Plugin\Supervisor\Status\WorkerInfo;

/**
 * @internal
 */
final class WorkersCommand extends Command
{
    public const COMMAND = 'workers';
    public const DESCRIPTION = 'Show workers status';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $this->assertServerIsRunning($args['pidFile']);

        echo "❯ Workers\n";

        $bus = new SocketFileMessageBus($args['socketFile']);
        $supervisorStatus = $bus->dispatch(new ContainerGetCommand(SupervisorStatus::class))->await();
        \assert($supervisorStatus instanceof SupervisorStatus);
        $workers = $supervisorStatus->getWorkers();

        if (\count($workers) > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Count',
                ])
                ->addRows(\array_map(array: $workers, callback: static fn(WorkerInfo $w) => [
                    $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                    $w->name,
                    $w->count,
                ]));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no workers</>\n";
        }

        return 0;
    }
}