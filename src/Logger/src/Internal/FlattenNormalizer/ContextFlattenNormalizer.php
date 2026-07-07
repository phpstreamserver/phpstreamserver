<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final class ContextFlattenNormalizer
{
    private const MAX_DEPTH = 32;

    private function __construct()
    {
    }

    /**
     * @template T of array<null|int|float|bool|string|\Stringable>|null|int|float|bool|string|\Stringable
     * @psalm-return (T is array ? array<null|int|bool|string|\Stringable> : null|int|float|bool|string|\Stringable)
     */
    public static function flatten(mixed $data): array|null|int|float|bool|string|\Stringable
    {
        return self::doFlatten($data, 0);
    }

    private static function doFlatten(mixed $data, int $depth): array|null|int|float|bool|string|\Stringable
    {
        if ($depth > self::MAX_DEPTH) {
            return '[max-depth]';
        }

        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::doFlatten($value, $depth + 1);
            }

            return $data;
        }

        if (\is_null($data) || \is_scalar($data)
            || $data instanceof FlattenException
            || $data instanceof FlattenDateTime
            || $data instanceof FlattenObject
            || $data instanceof FlattenResource
            || $data instanceof FlattenEnum
        ) {
            return $data;
        }

        if ($data instanceof \Throwable) {
            return FlattenException::create($data);
        }

        if ($data instanceof \DateTimeInterface) {
            return FlattenDateTime::create($data);
        }

        if ($data instanceof \UnitEnum) {
            return FlattenEnum::create($data);
        }

        if ($data instanceof \JsonSerializable) {
            try {
                return self::doFlatten($data->jsonSerialize(), $depth + 1);
            } catch (\Throwable) {
                return FlattenObject::create($data);
            }
        }

        if ($data instanceof \Stringable) {
            try {
                return $data->__toString();
            } catch (\Throwable) {
                return FlattenObject::create($data);
            }
        }

        if (\is_object($data)) {
            return FlattenObject::create($data);
        }

        if (\is_resource($data) || \get_debug_type($data) === 'resource (closed)') {
            /** @psalm-suppress PossiblyInvalidArgument */
            return FlattenResource::create($data);
        }

        return \sprintf('[unknown(%s)]', \get_debug_type($data));
    }
}
