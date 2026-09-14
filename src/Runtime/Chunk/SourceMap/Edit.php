<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Chunk\SourceMap;

/**
 * Replacement of a range of a text, such as a line splice replaced by nothing
 * or a comment replaced by a space.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Runtime
 */
final class Edit
{
    public function __construct(
        /**
         * Offset in the text of the first replaced byte
         *
         * @var int<0, max>
         */
        public readonly int $offset,
        /**
         * Number of the replaced bytes
         *
         * @var int<0, max>
         */
        public readonly int $length,
        /**
         * The text that takes the place of the replaced bytes
         */
        public readonly string $replacement = '',
    ) {}
}
