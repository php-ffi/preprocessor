<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime;

use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;

interface SourceExecutorInterface
{
    /**
     * Returns the output of the file, the one that replaces the directive.
     *
     * @return iterable<int, TaggedChunk>
     * @throws PreprocessorException in case of the file cannot be preprocessed
     * @throws SourceExceptionInterface in case of the file cannot be read
     * @throws \Throwable in case of the diagnostic listener stops the preprocessing
     */
    public function execute(FileInterface $file): iterable;
}
