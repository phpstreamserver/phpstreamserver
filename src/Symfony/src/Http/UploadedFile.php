<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\ByteStream\WritableResourceStream;
use PHPStreamServer\Symfony\Http\Multipart\Multipart;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile as BaseUploadedFile;

final class UploadedFile extends BaseUploadedFile
{
    public function __construct(private Multipart $multipart)
    {
        $path = \sys_get_temp_dir() . DIRECTORY_SEPARATOR . \uniqid('phpss-upload');
        $filename = $multipart->getFileName();
        $contentType = $multipart->getHeaderValue('content-type');

        parent::__construct($path, $filename, $contentType, -1);
    }

    public function getSize(): int
    {
        return $this->multipart->getSize();
    }

    public function getContent(): string
    {
        return $this->multipart->getContents();
    }

    public function getError(): int
    {
        return \UPLOAD_ERR_OK;
    }

    public function isValid(): bool
    {
        return true;
    }

    public function createTempFile(): self
    {
        $this->move($this->getPath(), $this->getBasename());

        return $this;
    }

    public function move(string $directory, ?string $name = null): File
    {
        $target = $this->getTargetFile($directory, $name);
        $file = new WritableResourceStream(\fopen($target->getPathname(), 'w'));

        $this->multipart->rewind();
        while ('' !== $chunk = $this->multipart->read(16384)) {
            $file->write($chunk);
        }

        return $target;
    }
}
