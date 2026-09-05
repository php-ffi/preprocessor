<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#undef` removes a macro (6.10.5.5).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
final class UndefDirectiveHandler implements DirectiveHandlerInterface
{
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        throw new \BadMethodCallException('The handler is not implemented yet');
    }
}
