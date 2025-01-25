<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\FlattenNormalizer;

/**
 * @internal
 */
final readonly class FlattenResource
{
    private function __construct(
        public string $type,
        public string|null $wrapperType,
        public string|null $streamType,
        public string|null $mode,
        public string|null $uri,
    ) {
    }

    /**
     * @param resource $resource
     */
    public static function create($resource): self
    {
        try {
            $meta = \stream_get_meta_data($resource);
        } catch (\TypeError) {
            $meta = [];
        }

        return new self(
            type: \get_resource_type($resource),
            wrapperType: $meta['wrapper_type'] ?? null,
            streamType: $meta['stream_type'] ?? null,
            mode: $meta['mode'] ?? null,
            uri: $meta['uri'] ?? null,
        );
    }

    public function toString(): string
    {
        $attr = '';
        if ($this->uri !== null) {
            $attr = ': ' . $this->uri;
        }

        return \sprintf('[resource(%s)%s]', $this->type, $attr === '' ? '' : $attr);
    }
}
