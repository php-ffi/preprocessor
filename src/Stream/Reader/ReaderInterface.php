<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Reads a file by the rules that hold for all of its text, whatever the text
 * means: the translation phases 1 to 3.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream
 */
interface ReaderInterface
{
    /**
     * Returns the text of the file as chunks in the order of the file,
     * made while they are iterated.
     *
     * @return iterable<int, UntaggedChunk>
     * @throws InternalException in case of the file cannot be read
     * @throws SyntaxErrorException in case of the file ends in a comment
     */
    public function read(FileInterface $file): iterable;
}
