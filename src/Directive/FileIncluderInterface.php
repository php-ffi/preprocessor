<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Preprocesses a file that `#include` includes.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
interface FileIncluderInterface
{
    /**
     * Returns the output of the file, the one that replaces the directive.
     *
     * @return iterable<int, TaggedChunk>
     * @throws PreprocessorException in case of the file cannot be included
     */
    public function includeFile(FileInterface $file): iterable;
}
