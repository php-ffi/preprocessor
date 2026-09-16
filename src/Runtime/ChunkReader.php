<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime;

use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use FFI\Preprocessor\Runtime\Exception\UnclosedCommentException;
use FFI\Preprocessor\Runtime\Exception\UnmatchedTextException;
use FFI\Preprocessor\Runtime\Reader\BlockReader;
use FFI\Preprocessor\Runtime\Reader\ReaderInterface;
use FFI\Preprocessor\Runtime\Segmenter\LineSegmenter;
use FFI\Preprocessor\Runtime\Segmenter\SegmenterInterface;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Splits a file into the chunks of source text and the directives, without
 * knowing which of them are skipped or what the directives do.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
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
     * @throws UnclosedCommentException in case of the file ends in a comment
     * @throws UnmatchedTextException in case of the text cannot be matched
     * @throws SourceExceptionInterface in case of the file cannot be read
     */
    public function read(FileInterface $file): iterable
    {
        return $this->segmenter->segment($this->reader->read($file));
    }
}
