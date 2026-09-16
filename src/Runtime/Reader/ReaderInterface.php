<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Reader;

use FFI\Preprocessor\Runtime\Chunk\UntaggedChunk;
use FFI\Preprocessor\Runtime\Exception\UnclosedCommentException;
use FFI\Preprocessor\Runtime\Exception\UnmatchedTextException;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Reads a file by the rules that hold for all of its text, whatever the text
 * means: the translation phases 1 to 3.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Runtime
 */
interface ReaderInterface
{
    /**
     * Returns the text of the file as chunks in the order of the file,
     * made while they are iterated.
     *
     * @return iterable<int, UntaggedChunk>
     * @throws UnclosedCommentException in case of the file ends in a comment
     * @throws UnmatchedTextException in case of the text cannot be matched
     * @throws SourceExceptionInterface in case of the file cannot be read
     */
    public function read(FileInterface $file): iterable;
}
