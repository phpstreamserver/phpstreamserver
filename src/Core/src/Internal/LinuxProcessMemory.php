<?php

declare(strict_types=1);

namespace PHPStreamServer\Core\Internal;

/**
 * @internal
 */
final class LinuxProcessMemory
{
    private function __construct()
    {
    }

    public static function get(int $pid): int
    {
        if (\PHP_OS_FAMILY !== 'Linux') {
            throw new \RuntimeException(\sprintf('%s is only supported on Linux', self::class));
        }

        if ($pid <= 0) {
            throw new \InvalidArgumentException(\sprintf('Invalid process PID: %d', $pid));
        }

        if (PHP_VERSION_ID < 80300 && \is_file("/proc/$pid/status")) {
            $status = \file_get_contents("/proc/$pid/status");
            if ($status === false || \preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $matches) !== 1) {
                $vmrss = 0;
            } else {
                /** @psalm-suppress UndefinedVariable */
                $vmrss = (int) $matches[1] * 1024;
            }
        } elseif (PHP_VERSION_ID >= 80300 && \is_file("/proc/$pid/statm")) {
            $statm = \file_get_contents("/proc/$pid/statm");
            if ($statm === false) {
                $vmrss = 0;
            } else {
                $pagesize = \posix_sysconf(POSIX_SC_PAGESIZE);
                $statm = \explode(' ', \trim($statm));
                $vmrss = (int) ($statm[1] ?? 0) * $pagesize;
            }
        } else {
            $vmrss = 0;
        }

        return $vmrss;
    }
}
