<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final readonly class FlattenEnum implements \Stringable
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

    public function __toString()
    {
        return \sprintf('[enum(%s): %s]', $this->class, $this->value);
    }
}
