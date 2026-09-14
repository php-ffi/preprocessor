<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime;

use FFI\Preprocessor\Runtime\Chunk\SourceMap\SourceMap;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Base of the chunks: the text without the line splices and the comments, and
 * the place it has been read from.
 */
abstract class Chunk implements \Stringable
{
    public function __construct(
        /**
         * The text of the chunk without the line splices, the comments replaced by a space each
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
         * The bytes of the file the text skips: the line splices and the comments
         */
        public readonly SourceMap $map,
    ) {}

    /**
     * Returns the offset in the file of the byte at the given offset of the
     * text of the chunk.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    public function getPhysicalOffset(int $offset): int
    {
        return $this->offset + $this->map->getPhysicalOffset($offset);
    }

    /**
     * Returns the size in the file of the part of the text of the chunk,
     * the line splices inside it included.
     *
     * @param int<0, max> $offset
     * @param int<1, max> $length
     * @return int<1, max>
     */
    public function getPhysicalLength(int $offset, int $length): int
    {
        return $this->map->getPhysicalLength($offset, $length);
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
