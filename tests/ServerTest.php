<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Command\GetServerStatusCommand;
use PHPStreamServer\Core\Plugin\System\ServerStatus;
use PHPStreamServer\Test\data\PHPSSTestCase;

final class ServerTest extends PHPSSTestCase
{
    public function testServerIsStarted(): void
    {
        $serverStatus = $this->dispatch(new GetServerStatusCommand());

        $this->assertInstanceOf(ServerStatus::class, $serverStatus, 'Server did not return a ServerStatus instance');
    }
}
