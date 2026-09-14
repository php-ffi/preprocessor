<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Segmenter;

use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use FFI\Preprocessor\Runtime\Chunk\UntaggedChunk;
use FFI\Preprocessor\Runtime\Exception\UnmatchedTextException;

/**
 * Tells the source text from the preprocessing directives in the cleaned
 * text of a file, without knowing which of them are skipped or what the
 * directives do.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Runtime
 */
interface SegmenterInterface
{
    /**
     * Returns the tagged chunks of the untagged ones in the order of the
     * file, made while they are iterated.
     *
     * @param iterable<int, UntaggedChunk> $chunks the consecutive pieces of the file
     * @return iterable<int, TaggedChunk>
     * @throws UnmatchedTextException in case of the text cannot be matched
     */
    public function segment(iterable $chunks): iterable;
}
