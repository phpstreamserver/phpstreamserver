<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final readonly class FlattenDateTime
{
    private function __construct(
        public \DateTimeImmutable $dt,
        public string $class,
    ) {
    }

    public static function create(\DateTimeInterface $dt): self
    {
        return new self(\DateTimeImmutable::createFromInterface($dt), $dt::class);
    }

    public function toString(string $format = \DateTimeInterface::RFC3339): string
    {
        return \sprintf('[datetime(%s): %s]', $this->class, $this->dt->format($format));
    }

    public function format(string $format): string
    {
        return $this->dt->format($format);
    }
}
