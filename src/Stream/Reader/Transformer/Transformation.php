<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\Transformer;

use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;

/**
 * Result of {@see TransformerInterface::transform()}.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream
 */
final class Transformation
{
    public function __construct(
        /**
         * The part of the text the step is done with
         */
        public readonly UntaggedChunk $ready,
        /**
         * The part of the text that waits for the continuation of the file, as it has been read
         */
        public readonly UntaggedChunk $rest,
    ) {}
}
