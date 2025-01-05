<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class DeleteUploadedFilesListener
{
    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $files = $event->getRequest()->files->all();

        \array_walk_recursive($files, static function (UploadedFile $file) {
            $path = $file->getRealPath();
            if ($path !== false && \file_exists($path)) {
                \unlink($path);
            }
        });
    }
}
