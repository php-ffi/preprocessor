<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime;

use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use Phplrt\Contracts\Source\FileInterface;

interface SourceExecutorInterface
{
    /**
     * Returns the output of the file, the one that replaces the directive.
     *
     * @return iterable<int, TaggedChunk>
     * @throws PreprocessorException in case of the file cannot be included
     */
    public function execute(FileInterface $file): iterable;
}
