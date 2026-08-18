<?php

declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

\phpss_start();
\register_shutdown_function(\phpss_stop(...));

\pcntl_async_signals(true);
\pcntl_signal(SIGINT, static function (): void {
    \phpss_stop();
    echo "\n";
    exit(128 + SIGINT);
});

function phpss_create_command(string $command): string
{
    return \sprintf('exec %s -d ffi.enable=true %s/server.php %s', PHP_BINARY, __DIR__, $command);
}

function phpss_start(): void
{
    \exec(\phpss_create_command('start -d') . ' > /dev/null 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        throw new \RuntimeException('Failed to start server');
    }
}

function phpss_stop(): void
{
    \exec(\phpss_create_command('stop') . ' > /dev/null 2>&1');
}
