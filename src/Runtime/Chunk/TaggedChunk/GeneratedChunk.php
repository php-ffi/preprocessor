<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Chunk\TaggedChunk;

use FFI\Preprocessor\Runtime\Chunk\SourceMap\SourceMap;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Text the preprocessor produces, not reads: the replacement of a
 * macro invocation, the integers of `#embed`, a `#pragma` passed through. It
 * stands for the place of what it replaces, every offset of its text maps to
 * that place.
 */
final class GeneratedChunk extends TaggedChunk
{
    /**
     * @param int<0, max> $offset the offset in the file of what the text
     *        replaces, such as the macro name of an invocation
     */
    public function __construct(string $content, FileInterface $source, int $offset)
    {
        parent::__construct($content, $source, $offset, SourceMap::createEmpty());
    }

    public function getPhysicalOffset(int $offset): int
    {
        return $this->offset;
    }

    public function getPhysicalLength(int $offset, int $length): int
    {
        return 1;
    }
}
