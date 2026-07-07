<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final class FlattenDateTime implements \Stringable
{
    private string $format = \DateTimeInterface::RFC3339;

    private function __construct(
        public readonly \DateTimeImmutable $dt,
        public readonly string $class,
    ) {
    }

    public static function create(\DateTimeInterface $dt): self
    {
        return new self(\DateTimeImmutable::createFromInterface($dt), $dt::class);
    }

    public function withFormat(string $format): self
    {
        $that = clone $this;
        $that->format = $format;
        return $that;
    }

    public function __toString()
    {
        return \sprintf('[datetime(%s): %s]', $this->class, $this->dt->format($this->format));
    }
}
