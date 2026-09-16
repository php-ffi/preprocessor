<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Reader\Transformer;

use FFI\Preprocessor\Runtime\Chunk\UntaggedChunk;
use FFI\Preprocessor\Runtime\Exception\UnclosedCommentException;
use FFI\Preprocessor\Runtime\Exception\UnmatchedTextException;

/**
 * Step of the translation phases 1 to 3 over the text of a file that arrives
 * in pieces.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Runtime
 */
interface TransformerInterface
{
    /**
     * Returns the part of the chunk the step is done with and the rest,
     * which depends on what the file continues with. The rest comes back as
     * the start of the next chunk, and nothing is left in it once the
     * chunk is final.
     *
     * @param bool $isFinal whether the chunk ends the file
     * @throws UnclosedCommentException in case of the file ends in a block comment that is not closed
     * @throws UnmatchedTextException in case of the text cannot be matched
     */
    public function transform(UntaggedChunk $input, bool $isFinal): Transformation;
}
