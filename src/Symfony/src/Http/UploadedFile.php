<?php

declare(strict_types=1);

namespace PHPStreamServer\Symfony\Http;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\WritableResourceStream;
use PHPStreamServer\Symfony\Http\Multipart\Multipart;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile as BaseUploadedFile;

final class UploadedFile extends BaseUploadedFile
{
    private int $error;

    public function __construct(private readonly Multipart $multipart)
    {
        $path = \sprintf('%s/phpss-upload-%s', \sys_get_temp_dir(), \uniqid());
        $filename = $multipart->getFileName();
        $contentType = $multipart->getHeaderValue('content-type');
        \assert(\is_string($filename));
        \assert(\is_string($contentType));

        parent::__construct($path, $filename, $contentType, -1);

        try {
            $this->move($this->getPath(), $this->getBasename());
            $this->error = \UPLOAD_ERR_OK;
        } catch (ClosedException|\Error) {
            $this->error = \UPLOAD_ERR_CANT_WRITE;
        }
    }

    public function getSize(): int
    {
        return $this->multipart->getSize();
    }

    public function getContent(): string
    {
        return $this->multipart->getContents();
    }

    public function isValid(): bool
    {
        return $this->error === \UPLOAD_ERR_OK;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        return match ($this->error) {
            \UPLOAD_ERR_CANT_WRITE => \sprintf('The file "%s" could not be written on disk.', $this->getClientOriginalName()),
            default => '',
        };
    }

    /**
     * @throws ClosedException
     * @throws \Error
     */
    public function move(string $directory, string|null $name = null): File
    {
        $target = $this->getTargetFile($directory, $name);

        try {
            \set_error_handler(static fn(int $errno, string $errstr): never => throw new \Error($errstr));
            $file = new WritableResourceStream(\fopen($target->getPathname(), 'w'));
        } finally {
            \restore_error_handler();
        }

        $this->multipart->rewind();
        while ('' !== $chunk = $this->multipart->read(16384)) {
            $file->write($chunk);
        }
        $file->close();

        return $target;
    }
}
