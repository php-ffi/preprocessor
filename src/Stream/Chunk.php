<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream;

use FFI\Preprocessor\Stream\Chunk\SourceMap;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Base of the chunks: the text without the line splices and the comments, and
 * the place it has been read from.
 */
abstract class Chunk implements ChunkInterface
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

    public function getPhysicalOffset(int $offset): int
    {
        return $this->offset + $this->map->getPhysicalOffset($offset);
    }

    public function getPhysicalLength(int $offset, int $length): int
    {
        return $this->map->getPhysicalLength($offset, $length);
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
