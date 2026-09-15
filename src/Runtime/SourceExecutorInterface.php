<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime;

use FFI\Preprocessor\Directive\Exception\IncludeDepthException;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\Exception\UnknownDirectiveException;
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
     * @throws \Throwable in case of the file cannot be read or a directive cannot be executed
     */
    public function execute(FileInterface $file): iterable;
}
