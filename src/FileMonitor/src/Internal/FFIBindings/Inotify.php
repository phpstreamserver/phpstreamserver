<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal\FFIBindings;

use FFI\CData;

/**
 * @internal
 *
 * @psalm-suppress InvalidPassByReference FFI C data is not represented accurately by Psalm.
 * @psalm-suppress PossiblyNullArgument
 * @psalm-suppress PossiblyNullArrayAccess
 * @psalm-suppress PossiblyNullPropertyAssignment
 * @psalm-suppress PossiblyNullPropertyFetch
 * @psalm-suppress PossiblyNullReference
 * @psalm-suppress UndefinedMethod
 * @psalm-suppress UndefinedPropertyAssignment
 * @psalm-suppress UndefinedPropertyFetch
 * @psalm-suppress PossiblyInvalidArgument
 */
final class Inotify
{
    public const IN_MODIFY = 0x00000002;
    public const IN_MOVED_FROM = 0x00000040;
    public const IN_MOVED_TO = 0x00000080;
    public const IN_CREATE = 0x00000100;
    public const IN_DELETE = 0x00000200;
    public const IN_Q_OVERFLOW = 0x00004000;
    public const IN_IGNORED = 0x00008000;
    public const IN_ISDIR = 0x40000000;

    private const EAGAIN = 11;
    private const READ_BUFFER_SIZE = 8192;

    private const CDEF = <<<'CDEF'
        struct inotify_event {
            int wd;
            uint32_t mask;
            uint32_t cookie;
            uint32_t len;
            char name[0];
        };

        int inotify_init(void);
        int inotify_add_watch(int fd, const char *pathname, uint32_t mask);
        int inotify_rm_watch(int fd, int wd);
        int close(int fd);
        ssize_t read(int fd, void *buf, size_t count);
        extern int errno;
    CDEF;

    private readonly \FFI $ffi;

    private int $fd;

    /**
     * @var resource|null
     */
    private mixed $stream = null;

    public function __construct()
    {
        if (\PHP_OS_FAMILY !== 'Linux') {
            throw new \LogicException('Inotify is only supported on Linux');
        }

        $this->ffi = \FFI::cdef(self::CDEF);
        $this->fd = $this->ffi->inotify_init();

        if ($this->fd === -1) {
            throw new \RuntimeException('Unable to initialize inotify: ' . \posix_strerror($this->ffi->errno));
        }

        if (false === $stream = \fopen(\sprintf('php://fd/%d', $this->fd), 'r')) {
            $this->ffi->close($this->fd);
            $this->fd = -1;

            throw new \RuntimeException('Unable to create a stream for the inotify descriptor');
        }

        $this->stream = $stream;
        \stream_set_blocking($this->stream, false);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return resource
     */
    public function getStream(): mixed
    {
        if (!\is_resource($this->stream)) {
            throw new \LogicException('The inotify descriptor is closed');
        }

        return $this->stream;
    }

    public function addWatch(string $pathname, int $mask): int
    {
        if ($this->fd === -1) {
            throw new \LogicException('The inotify descriptor is closed');
        }

        $watchDescriptor = (int) $this->ffi->inotify_add_watch($this->fd, $pathname, $mask);

        if ($watchDescriptor === -1) {
            throw new \RuntimeException(\sprintf('Unable to watch directory "%s": %s', $pathname, \posix_strerror($this->ffi->errno)));
        }

        return $watchDescriptor;
    }

    public function removeWatch(int $watchDescriptor): void
    {
        if ($this->fd === -1) {
            throw new \LogicException('The inotify descriptor is closed');
        }

        $this->ffi->inotify_rm_watch($this->fd, $watchDescriptor);
    }

    /**
     * @return list<array{wd: int, mask: int, name: string}>
     */
    public function read(): array
    {
        if ($this->fd === -1) {
            throw new \LogicException('The inotify descriptor is closed');
        }

        $ffi = $this->ffi;
        $eventType = $ffi->type('struct inotify_event');
        $eventPointerType = $ffi->type('struct inotify_event *');
        $eventSize = \FFI::sizeof($eventType);
        $buffer = $ffi->new('char[' . self::READ_BUFFER_SIZE . ']');
        $bytesRead = $ffi->read($this->fd, $buffer, self::READ_BUFFER_SIZE);

        if ($bytesRead === -1) {
            if ($ffi->errno === self::EAGAIN) {
                return [];
            }

            throw new \RuntimeException('Unable to read inotify events: ' . \posix_strerror($this->ffi->errno));
        }

        $events = [];
        for ($offset = 0; $offset < $bytesRead;) {
            $eventPointer = $ffi->cast($eventPointerType, \FFI::addr($buffer[$offset]));

            $wd = $eventPointer->wd instanceof CData ? $eventPointer->wd->cdata : (int) $eventPointer->wd;
            $mask = $eventPointer->mask instanceof CData ? $eventPointer->mask->cdata : (int) $eventPointer->mask;
            $len = $eventPointer->len instanceof CData ? $eventPointer->len->cdata : (int) $eventPointer->len;

            $events[] = [
                'wd' => $wd,
                'mask' => $mask,
                'name' => $len === 0 ? '' : \FFI::string($eventPointer->name),
            ];

            $offset += $eventSize + $len;
        }

        return $events;
    }

    public function close(): void
    {
        if (\is_resource($this->stream)) {
            $stream = $this->stream;
            $this->stream = null;
            \fclose($stream);
        }

        if ($this->fd !== -1) {
            $this->ffi->close($this->fd);
            $this->fd = -1;
        }
    }
}
