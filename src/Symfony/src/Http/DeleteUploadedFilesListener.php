<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class DeleteUploadedFilesListener
{
    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $files = $event->getRequest()->files->all();

        \array_walk_recursive($files, static function (UploadedFile $file) {
            if (\file_exists($file->getRealPath())) {
                \unlink($file->getRealPath());
            }
        });
    }
}
