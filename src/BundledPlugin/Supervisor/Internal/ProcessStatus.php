<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal;

/**
 * @internal
 */
final class ProcessStatus
{
    public int $pid;
    public bool $detached = false;
    public bool $blocked = false;
    public int $time;

    public function __construct(int $pid)
    {
        $this->pid = $pid;
        $this->time = \hrtime(true);
    }
}