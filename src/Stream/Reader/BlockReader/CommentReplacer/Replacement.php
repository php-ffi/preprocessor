<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader\CommentReplacer;

/**
 * A text with its comments replaced by a space each (translation phase 3),
 * and what the replacements took from the file.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class Replacement
{
    public function __construct(
        /**
         * The text with the comments replaced
         */
        public readonly string $text,
        /**
         * Offsets in {@see $text} of the bytes after the spaces that replace
         * the comments, in ascending order
         *
         * @var list<int<1, max>>
         */
        public readonly array $offsets = [],
        /**
         * Number of the bytes each comment takes in the file besides the
         * space that replaces it, of the same index as its offset
         *
         * @var list<int<1, max>>
         */
        public readonly array $sizes = [],
    ) {}
}
