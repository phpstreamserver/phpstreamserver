<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Core\Message\GetServerStatusCommand;
use PHPStreamServer\Core\Plugin\System\Status\ServerStatus;
use PHPStreamServer\Test\data\PHPSSTestCase;

final class ServerTest extends PHPSSTestCase
{
    public function testServerIsStarted(): void
    {
        $serverStatus = $this->dispatch(new GetServerStatusCommand());

        $this->assertInstanceOf(ServerStatus::class, $serverStatus, 'Server is not running');
    }
}
