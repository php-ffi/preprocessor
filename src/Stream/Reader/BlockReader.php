<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;
use FFI\Preprocessor\Stream\Reader\Transformer\CommentReplacer;
use FFI\Preprocessor\Stream\Reader\Transformer\SpliceRemover;
use FFI\Preprocessor\Stream\Reader\Transformer\TransformerInterface;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
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
 * @psalm-internal FFI\Preprocessor\Stream
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
     * @throws InternalException in case of the file cannot be read
     * @throws SyntaxErrorException in case of the file ends in a comment
     */
    public function read(FileInterface $file): \Generator
    {
        // The byte order mark is read whole whatever the size of a block is
        $block = $this->readBlock($file, 0, \max($this->blockSize, \strlen(self::BYTE_ORDER_MARK)));
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

            $block = $this->readBlock($file, $position, $this->blockSize);
            $isFinal = $block === '';
            $offset = $position;
            $position += \strlen($block);
        }
    }

    /**
     * @param int<0, max> $position
     * @param int<1, max> $size
     * @throws InternalException in case of the file cannot be read
     */
    private function readBlock(FileInterface $file, int $position, int $size): string
    {
        try {
            return $file->read($position, $size);
        } catch (SourceExceptionInterface $e) {
            throw InternalException::becauseSourceIsNotReadable($file, $e);
        }
    }
}
