<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * What a directive handler may do with the preprocessing.
 */
final class DirectiveContext
{
    /**
     * The blanks that may follow the body of a directive.
     */
    private const BLANKS = " \t\f\x0B";

    public function __construct(
        /**
         * The open conditional sections, `#if` and `#endif` change them
         */
        public readonly OutputStack $output,
        /**
         * Receives the messages of `#error` and `#warning`
         */
        public readonly DiagnosticListenerInterface $diagnostic,
    ) {}

    /**
     * Warns about the tokens in the body of a directive that takes none, such
     * as `#endif X`. Does nothing in case of the body is empty.
     *
     * @throws \Throwable in case of the diagnostic listener stops the preprocessing
     */
    public function reportExtraTokens(SourceDirective $directive): void
    {
        $length = \strlen(\rtrim($directive->body, self::BLANKS));

        if ($length === 0) {
            return;
        }

        $this->diagnostic->notify(new Diagnostic(
            severity: Severity::Warning,
            message: \sprintf('Extra tokens at the end of #%s directive', $directive->name),
            source: $directive->source,
            offset: $directive->getPhysicalOffset($directive->bodyOffset),
            length: $directive->getPhysicalLength($directive->bodyOffset, $length),
        ));
    }
}
