<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final readonly class FlattenEnum
{
    private function __construct(
        public string $class,
        public string $value,
    ) {
    }

    public static function create(\UnitEnum $enum): self
    {
        return new self($enum::class, $enum->name);
    }

    public function toString(): string
    {
        return \sprintf('[enum(%s): %s]', $this->class, $this->value);
    }
}
