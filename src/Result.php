<?php

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;

/**
 * @template-implements \IteratorAggregate<array-key, TaggedChunk>
 */
final class Result implements
    \IteratorAggregate,
    \Stringable
{
    public function __construct(
        /**
         * @var iterable<array-key, TaggedChunk>
         */
        private readonly iterable $chunks,
    ) {}

    public function getIterator(): \Traversable
    {
        yield from $this->chunks;
    }

    public function __toString(): string
    {
        $buffer = '';

        foreach ($this->chunks as $chunk) {
            $buffer .= $chunk->content;
        }

        return $buffer;
    }
}
