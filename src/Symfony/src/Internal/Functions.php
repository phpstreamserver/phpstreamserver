<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Internal;

/**
 * @internal
 */
final class Functions
{
    private function __construct()
    {
    }

    /**
     * @psalm-suppress UndefinedFunction
     * @psalm-suppress UndefinedConstant
     */
    public static function cpuCount(): int
    {
        if (\PHP_VERSION_ID >= 80300) {
            return \posix_sysconf(\POSIX_SC_NPROCESSORS_ONLN);
        } elseif (\DIRECTORY_SEPARATOR === '/' && \function_exists('shell_exec')) {
            return \strtolower(\PHP_OS) === 'darwin' ? (int) \shell_exec('sysctl -n machdep.cpu.core_count') : (int) \shell_exec('nproc');
        } else {
            return 1;
        }
    }
}
