<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final readonly class FlattenException implements \Stringable
{
    /**
     * @param class-string $class
     */
    private function __construct(
        public string $class,
        public string $message,
        public int $code,
        public string $file,
        public int $line,
        public array $trace,
        public self|null $previous,
    ) {
    }

    public static function create(\Throwable $exception): self
    {
        $previous = $exception->getPrevious();
        $trace = $exception->getTrace();

        foreach ($trace as &$traceItem) {
            unset($traceItem['args']);
        }

        return new self(
            class: self::parseAnonymousClass($exception::class),
            message: $exception->getMessage(),
            code: (int) $exception->getCode(),
            file: $exception->getFile(),
            line: $exception->getLine(),
            trace: $trace,
            previous: $previous === null ? null : self::create($previous),
        );
    }

    /**
     * Gets a multiline string representation of the thrown object with a stack race, same as the original exception
     */
    public function __toString(): string
    {
        $out = \sprintf("%s: %s in %s:%d\nStack trace:\n", self::parseAnonymousClass($this->class), $this->message, $this->file, $this->line);
        $i = 0;
        foreach ($this->trace as $trace) {
            $file = $trace['file'] ?? null;
            $line = $trace['line'] ?? null;
            $class = $trace['class'] ?? null;
            $type = $trace['type'] ?? null;
            $function = $trace['function'] ?? '[unknown function]';
            $fileStr = $file !== null && $line !== null ? \sprintf('%s(%d)', $file, $line) : '[internal function]';
            $functionStr = $class !== null && $type !== null ? self::parseAnonymousClass($class) . $type . $function : $function;
            $out .= \sprintf("#%d %s: %s()\n", $i++, $fileStr, $functionStr);
        }
        return $out . \sprintf("#%d {main}", $i);
    }

    /**
     * Gets a short string representation suitable for logging
     */
    public function toString(): string
    {
        $str = $this->formatException('exception');
        if (null !== $previous = $this->previous) {
            do {
                $str .= ',' . $this->formatException('previous');
            } while (null !== $previous = $previous->previous);
        }

        return $str;
    }

    private function formatException(string $objectTitle): string
    {
        return \sprintf(
            "[%s(%s) code:%d]: %s at %s:%d",
            $objectTitle,
            $this->class,
            $this->code,
            $this->message,
            $this->file,
            $this->line,
        );
    }

    /**
     * @param class-string $class
     * @return class-string
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType, RiskyTruthyFalsyComparison
     */
    private static function parseAnonymousClass(string $class): string
    {
        return \str_contains($class, "@anonymous\0")
            ? (\get_parent_class($class) ?: \key(\class_implements($class)) ?: 'class') . '@anonymous'
            : $class
        ;
    }
}
