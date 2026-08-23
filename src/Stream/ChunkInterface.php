<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream;

use Phplrt\Contracts\Source\FileInterface;

/**
 * Chunk of a file in the order the reader meets it: either source text or a
 * directive. Its text is the one after the translation phase 2, without the
 * line splices.
 *
 * @property-read FileInterface $source the file the chunk has been read from, under its virtual pathname
 * @property-read int<0, max> $offset offset of the first byte of the chunk in the file
 */
interface ChunkInterface extends \Stringable
{
    /**
     * Returns the offset in the file of the byte at the given offset of the
     * text of the chunk.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    public function getPhysicalOffset(int $offset): int;

    /**
     * Returns the size in the file of the fragment of the text of the chunk,
     * the line splices inside it included.
     *
     * @param int<0, max> $offset
     * @param int<1, max> $length
     * @return int<1, max>
     */
    public function getPhysicalLength(int $offset, int $length): int;
}
