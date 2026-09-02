<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\Stream\ChunkInterface;
use FFI\Preprocessor\Stream\SourceDirective;

/**
 * Executes the directives of a name, such as `#define`, `#include` or `#if`.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10 Preprocessing directives
 */
interface DirectiveHandlerInterface
{
    /**
     * Executes the directive, returns the output that replaces it.
     *
     * @return iterable<int, ChunkInterface>
     * @throws PreprocessorException in case of the directive cannot be executed
     */
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable;
}
