<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\Config\MBConfig;

return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([
        __DIR__ . '/src/Server',
        __DIR__ . '/src/Plugins',
    ]);
};
