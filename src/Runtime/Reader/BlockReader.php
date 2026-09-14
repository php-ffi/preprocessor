<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Reader;

use FFI\Preprocessor\Runtime\Chunk\UntaggedChunk;
use FFI\Preprocessor\Runtime\Exception\UnclosedCommentException;
use FFI\Preprocessor\Runtime\Exception\UnmatchedTextException;
use FFI\Preprocessor\Runtime\Reader\Transformer\CommentReplacer;
use FFI\Preprocessor\Runtime\Reader\Transformer\SpliceRemover;
use FFI\Preprocessor\Runtime\Reader\Transformer\TransformerInterface;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Streaming reader: keeps no more of a file in memory than a block and what
 * the transformers hold back until the file continues. Reads the files in any
 * ASCII-compatible encoding, an UTF-8 byte order mark at the start of a file
 * is not a part of its text.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Runtime
 */
final class BlockReader implements ReaderInterface
{
    /**
     * Number of bytes read from a file at a time unless told otherwise.
     */
    public const DEFAULT_BLOCK_SIZE = 65_536;

    /**
     * UTF-8 byte order mark.
     */
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    public function __construct(
        /**
         * The steps of the translation phases in their order
         *
         * @var list<TransformerInterface>
         */
        private readonly array $transformers,
        /**
         * Number of bytes read from a file at a time
         *
         * @var int<1, max>
         */
        private readonly int $blockSize = self::DEFAULT_BLOCK_SIZE,
    ) {}

    /**
     * Creates the reader that removes the line splices and replaces the
     * comments, the translation phases 2 and 3.
     *
     * @param int<1, max> $blockSize number of bytes read from a file at a time
     */
    public static function createDefault(int $blockSize = self::DEFAULT_BLOCK_SIZE): self
    {
        return new self([new SpliceRemover(), new CommentReplacer()], $blockSize);
    }

    /**
     * @return \Generator<int, UntaggedChunk>
     * @throws UnclosedCommentException in case of the file ends in a comment
     * @throws UnmatchedTextException in case of the text cannot be matched
     * @throws \Throwable in case of internal error occurs
     */
    public function read(FileInterface $file): \Generator
    {
        // The byte order mark is read whole whatever the size of a block is
        $block = $file->read(0, \max($this->blockSize, \strlen(self::BYTE_ORDER_MARK)));

        $position = \strlen($block);
        $isFinal = $block === '';
        $offset = 0;

        if (\str_starts_with($block, self::BYTE_ORDER_MARK)) {
            $block = \substr($block, \strlen(self::BYTE_ORDER_MARK));
            $offset = \strlen(self::BYTE_ORDER_MARK);
        }

        $rests = \array_map(
            static fn(TransformerInterface $transformer): UntaggedChunk => UntaggedChunk::createFromString($file, ''),
            $this->transformers,
        );

        while (true) {
            $chunk = UntaggedChunk::createFromString($file, $block, $offset);

            foreach ($this->transformers as $i => $transformer) {
                $transformation = $transformer->transform($rests[$i]->concat($chunk), $isFinal);
                $rests[$i] = $transformation->rest;
                $chunk = $transformation->ready;
            }

            if (!$chunk->isEmpty()) {
                yield $chunk;
            }

            if ($isFinal) {
                return;
            }

            $block = $file->read($position, $this->blockSize);
            $isFinal = $block === '';
            $offset = $position;
            $position += \strlen($block);
        }
    }
}
