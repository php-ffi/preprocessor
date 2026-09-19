<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;

/**
 * `#warning` reports its message as a warning (6.10.7).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.7 Diagnostic directives
 */
final class WarningDirectiveHandler implements DirectiveHandlerInterface
{
    /**
     * @throws \Throwable in case of the diagnostic listener stops the preprocessing
     */
    public function handle(DirectiveChunk $directive, DirectiveContext $context): iterable
    {
        $context->diagnostic->notify(new Diagnostic(
            severity: Severity::Warning,
            message: \rtrim('#warning ' . $directive->body),
            source: $directive->source,
            offset: $directive->getPhysicalOffset(0),
            length: $directive->getPhysicalLength(0, \strlen($directive->content))
        ));

        return [];
    }
}
