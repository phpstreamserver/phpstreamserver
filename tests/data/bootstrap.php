<?php

declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

\phpss_start();
\register_shutdown_function(\phpss_stop(...));

function phpss_create_command(string $command): string
{
    return \sprintf('exec %s %s/server.php %s', PHP_BINARY, __DIR__, $command);
}

function phpss_start(): void
{
    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = \proc_open(\phpss_create_command('start -d'), $descriptor, $pipes);
    \usleep(100000);
    \proc_close($process);
}

function phpss_stop(): void
{
    \shell_exec(\phpss_create_command('stop'));
}
