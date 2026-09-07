<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream;

use FFI\Preprocessor\Stream\Chunk\TaggedChunk;
use FFI\Preprocessor\Stream\Exception\UnclosedCommentException;
use FFI\Preprocessor\Stream\Exception\UnmatchedTextException;
use FFI\Preprocessor\Stream\Exception\UnreadableFileException;
use FFI\Preprocessor\Stream\Reader\BlockReader;
use FFI\Preprocessor\Stream\Reader\ReaderInterface;
use FFI\Preprocessor\Stream\Segmenter\LineSegmenter;
use FFI\Preprocessor\Stream\Segmenter\SegmenterInterface;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Splits a file into the chunks of source text and the directives, without
 * knowing which of them are skipped or what the directives do.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream
 */
final class ChunkReader
{
    public function __construct(
        /**
         * Reads the text of a file cleaned by the translation phases 1 to 3
         */
        private readonly ReaderInterface $reader,
        /**
         * Tells the source text from the directives
         */
        private readonly SegmenterInterface $segmenter,
    ) {}

    /**
     * Creates the reader of the files by the rules of the standard.
     *
     * @param int<1, max> $blockSize number of bytes read from a file at a time
     */
    public static function createDefault(int $blockSize = BlockReader::DEFAULT_BLOCK_SIZE): self
    {
        return new self(BlockReader::createDefault($blockSize), new LineSegmenter());
    }

    /**
     * Returns the chunks of the file in the order of the file, made while
     * they are iterated.
     *
     * @return iterable<int, TaggedChunk>
     * @throws UnreadableFileException in case of the file cannot be read
     * @throws UnclosedCommentException in case of the file ends in a comment
     * @throws UnmatchedTextException in case of the text cannot be matched
     */
    public function read(FileInterface $file): iterable
    {
        return $this->segmenter->segment($this->reader->read($file));
    }
}
