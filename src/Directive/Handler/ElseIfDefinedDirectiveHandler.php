<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\OutputIndependentDirectiveHandlerInterface;
use FFI\Preprocessor\Stream\SourceDirective;

/**
 * `#elifdef` and `#elifndef` start the next branch of a conditional section by whether a macro is defined (6.10.2).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 */
final class ElseIfDefinedDirectiveHandler implements OutputIndependentDirectiveHandlerInterface
{
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        throw new \BadMethodCallException('The handler is not implemented yet');
    }
}
