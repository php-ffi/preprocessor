<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\SourceChunk;
use FFI\Preprocessor\Stream\SourceDirective;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Splits a file into the chunks of source text and the directives, without
 * knowing which of them are skipped or what the directives do.
 */
interface ReaderInterface
{
    /**
     * Returns the chunks of the file in the order of the file. The file is
     * read at once, the chunks are made while they are iterated.
     *
     * @return iterable<int, SourceChunk|SourceDirective>
     * @throws InternalException in case of the file cannot be read
     * @throws SyntaxErrorException in case of the file ends in a comment
     */
    public function read(FileInterface $file): iterable;
}
