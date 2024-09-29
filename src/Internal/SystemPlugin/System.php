<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin;

use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\ReloadCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\SchedulerCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\StartCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\StatusCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\StopCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\WorkersCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;

/**
 * @internal
 */
final class System extends Plugin
{
    private MasterProcess $masterProcess;

    public function __construct()
    {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterProcess = $masterProcess;

        if (!$this->masterProcess->isRunning()) {
            $this->masterProcess->set(ServerStatus::class, new ServerStatus());
        }
    }

    public function start(): void
    {
        /** @var ServerStatus $serverStatus */
        $serverStatus = $this->masterProcess->get(ServerStatus::class);
        $serverStatus->setRunning();
        $serverStatus->subscribeToWorkerMessages($this->masterProcess);
    }

    public function commands(): array
    {
        return [
            new StartCommand($this->masterProcess),
            new StopCommand($this->masterProcess),
            new ReloadCommand($this->masterProcess),
            new StatusCommand($this->masterProcess),
            new WorkersCommand($this->masterProcess),
            new ProcessesCommand($this->masterProcess),
            new ConnectionsCommand($this->masterProcess),
            new SchedulerCommand($this->masterProcess),
        ];
    }
}