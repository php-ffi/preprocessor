<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream;

use FFI\Preprocessor\Stream\Chunk\SourceMap;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Base of the chunks: the text without the line splices and the place it
 * has been read from.
 */
abstract class Chunk implements ChunkInterface
{
    public function __construct(
        /**
         * The text of the chunk without the line splices
         */
        public readonly string $content,
        /**
         * The file the chunk has been read from, under its virtual pathname
         */
        public readonly FileInterface $source,
        /**
         * Offset of the first byte of the chunk in the file
         *
         * @var int<0, max>
         */
        public readonly int $offset,
        /**
         * The line splices removed from the text
         */
        public readonly SourceMap $splices,
    ) {}

    public function getPhysicalOffset(int $offset): int
    {
        return $this->offset + $this->splices->getPhysicalOffset($offset);
    }

    public function getPhysicalLength(int $offset, int $length): int
    {
        return $this->splices->getPhysicalLength($offset, $length);
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
