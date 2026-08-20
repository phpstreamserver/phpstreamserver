<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\FileMonitor\Internal\FFIBindings;

use FFI\CData;
use PHPStreamServer\Core\Server;

/**
 * @internal
 *
 * @psalm-suppress InvalidPassByReference FFI C data is not represented accurately by Psalm.
 * @psalm-suppress MixedArgument
 * @psalm-suppress MixedAssignment
 * @psalm-suppress MixedMethodCall
 * @psalm-suppress MixedReturnStatement
 * @psalm-suppress PossiblyNullArgument
 * @psalm-suppress PossiblyNullArrayAccess
 * @psalm-suppress PossiblyNullPropertyAssignment
 * @psalm-suppress PossiblyNullReference
 * @psalm-suppress UndefinedMethod
 * @psalm-suppress UndefinedPropertyAssignment
 * @psalm-suppress UndefinedPropertyFetch
 */
final class FSEvents
{
    public const EVENT_FLAG_ROOT_CHANGED = 0x00000020;
    public const EVENT_FLAG_ITEM_REMOVED = 0x00000200;
    public const EVENT_FLAG_ITEM_RENAMED = 0x00000800;
    public const EVENT_FLAG_ITEM_IS_DIR = 0x00020000;
    public const EVENT_FLAG_ITEM_IS_SYMLINK = 0x00040000;

    private const CORE_SERVICES = '/System/Library/Frameworks/CoreServices.framework/CoreServices';
    private const CREATE_FLAG_NO_DEFER = 0x00000002;
    private const CREATE_FLAG_WATCH_ROOT = 0x00000004;
    private const CREATE_FLAG_FILE_EVENTS = 0x00000010;
    private const EVENT_ID_SINCE_NOW = -1;
    private const STRING_ENCODING_UTF8 = 0x08000100;
    private const RUN_LOOP_RUN_HANDLED_SOURCE = 4;

    private const CDEF = <<<'CDEF'
        typedef signed long CFIndex;
        typedef unsigned char Boolean;
        typedef double CFTimeInterval;
        typedef uint32_t CFStringEncoding;
        typedef const struct __CFAllocator *CFAllocatorRef;
        typedef const struct __CFString *CFStringRef;
        typedef const struct __CFArray *CFArrayRef;
        typedef struct __CFRunLoop *CFRunLoopRef;
        typedef CFStringRef CFRunLoopMode;

        typedef struct __FSEventStream *FSEventStreamRef;
        typedef const struct __FSEventStream *ConstFSEventStreamRef;
        typedef uint64_t FSEventStreamEventId;
        typedef uint32_t FSEventStreamCreateFlags;
        typedef uint32_t FSEventStreamEventFlags;

        typedef void (*FSEventStreamCallback)(
            ConstFSEventStreamRef streamRef,
            void *clientCallBackInfo,
            size_t numEvents,
            const char **eventPaths,
            const FSEventStreamEventFlags *eventFlags,
            const FSEventStreamEventId *eventIds
        );

        CFStringRef CFStringCreateWithCString(
            CFAllocatorRef alloc,
            const char *cStr,
            CFStringEncoding encoding
        );
        CFArrayRef CFArrayCreate(
            CFAllocatorRef allocator,
            const void **values,
            CFIndex numValues,
            const void *callBacks
        );
        void CFRelease(const void *cf);
        CFRunLoopRef CFRunLoopGetCurrent(void);
        int32_t CFRunLoopRunInMode(CFRunLoopMode mode, CFTimeInterval seconds, Boolean returnAfterSourceHandled);

        FSEventStreamRef FSEventStreamCreate(
            CFAllocatorRef allocator,
            FSEventStreamCallback callback,
            void *context,
            CFArrayRef pathsToWatch,
            FSEventStreamEventId sinceWhen,
            CFTimeInterval latency,
            FSEventStreamCreateFlags flags
        );
        void FSEventStreamScheduleWithRunLoop(
            FSEventStreamRef streamRef,
            CFRunLoopRef runLoop,
            CFRunLoopMode runLoopMode
        );
        Boolean FSEventStreamStart(FSEventStreamRef streamRef);
        void FSEventStreamStop(FSEventStreamRef streamRef);
        void FSEventStreamInvalidate(FSEventStreamRef streamRef);
        void FSEventStreamRelease(FSEventStreamRef streamRef);
    CDEF;

    private readonly \FFI $ffi;
    private readonly int $ownerPid;

    /**
     * @var list<array{path: string, flags: int}>
     */
    private array $events = [];

    private \Closure|null $callback = null;
    private CData|null $runLoopMode = null;
    private CData|null $stream = null;

    /**
     * @var list<CData>
     */
    private array $coreFoundationObjects = [];

    /**
     * @param list<string> $paths
     */
    public function __construct(array $paths)
    {
        if (\PHP_OS_FAMILY !== 'Darwin') {
            throw new \LogicException('FSEvents is only supported on Darwin (macOS)');
        }

        if ($paths === []) {
            throw new \InvalidArgumentException('At least one path is required for FSEvents');
        }

        $this->ffi = \FFI::cdef(self::CDEF, self::CORE_SERVICES);
        $this->ownerPid = \posix_getpid();

        try {
            $this->runLoopMode = $this->createString(\sprintf('%s.FSEvents', Server::NAME));
            $paths = $this->createPaths($paths);

            $events = &$this->events;
            $this->callback = static function (mixed $streamRef, mixed $clientCallBackInfo, int $numEvents, mixed $eventPaths, mixed $eventFlags, mixed $eventIds) use (&$events): void {
                for ($index = 0; $index < $numEvents; ++$index) {
                    $flags = $eventFlags[$index];
                    $events[] = [
                        'path' => \FFI::string($eventPaths[$index]),
                        'flags' => $flags instanceof CData ? $flags->cdata : (int) $flags,
                    ];
                }
            };

            $stream = $this->ffi->FSEventStreamCreate(null, $this->callback, null, $paths, self::EVENT_ID_SINCE_NOW, 0.0, self::CREATE_FLAG_NO_DEFER | self::CREATE_FLAG_WATCH_ROOT | self::CREATE_FLAG_FILE_EVENTS);
            if (self::isNull($stream)) {
                throw new \RuntimeException('Unable to create FSEvents stream');
            }

            $runLoop = $this->ffi->CFRunLoopGetCurrent();
            if (self::isNull($runLoop)) {
                throw new \RuntimeException('Unable to get the current Core Foundation run loop');
            }
            $this->ffi->FSEventStreamScheduleWithRunLoop($stream, $runLoop, $this->runLoopMode);

            if ($this->ffi->FSEventStreamStart($stream) === 0) {
                throw new \RuntimeException('Unable to start FSEvents stream');
            }

            $this->stream = $stream;
        } catch (\Throwable $exception) {
            $this->close();
            throw $exception;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return list<array{path: string, flags: int}>
     */
    public function read(): array
    {
        if ($this->stream === null) {
            throw new \LogicException('The FSEvents stream is closed');
        }
        if ($this->ownerPid !== \posix_getpid()) {
            throw new \LogicException('The FSEvents stream cannot be used after fork');
        }

        while ($this->ffi->CFRunLoopRunInMode($this->runLoopMode, 0.0, 1) === self::RUN_LOOP_RUN_HANDLED_SOURCE) {
            // Intentionally empty: drains all ready CF sources into $this->events
        }

        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function close(): void
    {
        if ($this->ownerPid !== \posix_getpid()) {
            return;
        }

        if ($this->stream !== null) {
            $this->ffi->FSEventStreamStop($this->stream);
            $this->ffi->FSEventStreamInvalidate($this->stream);
            $this->ffi->FSEventStreamRelease($this->stream);
            $this->stream = null;
        }

        foreach ($this->coreFoundationObjects as $object) {
            $this->ffi->CFRelease($object);
        }
        $this->coreFoundationObjects = [];

        $this->runLoopMode = null;
        $this->callback = null;
        $this->events = [];
    }

    /**
     * @param list<string> $paths
     */
    private function createPaths(array $paths): CData
    {
        $values = $this->ffi->new('const void *[' . \count($paths) . ']');

        foreach ($paths as $index => $path) {
            $values[$index] = $this->createString($path);
        }

        $pathsArray = $this->ffi->CFArrayCreate(null, $values, \count($paths), null);
        if (self::isNull($pathsArray)) {
            throw new \RuntimeException('Unable to create FSEvents paths array');
        }
        /** @var CData $pathsArray */
        $this->coreFoundationObjects[] = $pathsArray;

        return $pathsArray;
    }

    private function createString(string $value): CData
    {
        $string = $this->ffi->CFStringCreateWithCString(null, $value, self::STRING_ENCODING_UTF8);
        if (self::isNull($string)) {
            throw new \RuntimeException(\sprintf('Unable to create Core Foundation string for "%s"', $value));
        }
        /** @var CData $string */
        $this->coreFoundationObjects[] = $string;

        return $string;
    }

    private static function isNull(CData|null $zval): bool
    {
        return $zval === null || \FFI::isNull($zval);
    }
}
