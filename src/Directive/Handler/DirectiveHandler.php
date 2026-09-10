<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * Base of the handlers that read the body of a directive.
 */
abstract class DirectiveHandler implements DirectiveHandlerInterface
{
    /**
     * The blanks that may follow the body of a directive.
     */
    private const BLANKS = " \t\f\x0B";

    /**
     * An identifier at the start of a body
     */
    private const NAME_PATTERN = '/\A[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*+/';

    /**
     * Returns the macro name the body of a directive starts with.
     *
     * @return non-empty-string
     * @throws InvalidDirectiveBodyException in case of the body does not start with an identifier
     */
    protected function getMacroName(SourceDirective $directive): string
    {
        if (\preg_match(self::NAME_PATTERN, $directive->body, $matches) !== 1) {
            throw InvalidDirectiveBodyException::becauseMacroNameIsInvalid(
                $directive->source,
                $directive->getPhysicalOffset($directive->bodyOffset),
                $directive->name,
            );
        }

        return $matches[0];
    }

    /**
     * Warns about the tokens in the body of a directive that takes none, such
     * as `#endif X`, or none after the first `$from` bytes of the body, such
     * as `#ifdef X Y`. Does nothing in case of there are no such tokens.
     *
     * @param int<0, max> $from
     * @throws \Throwable in case of the diagnostic listener stops the preprocessing
     */
    protected function reportExtraTokens(DirectiveContext $context, SourceDirective $directive, int $from = 0): void
    {
        $rest = \substr($directive->body, $from);
        $start = \strspn($rest, self::BLANKS);
        $length = \strlen(\rtrim($rest, self::BLANKS)) - $start;

        if ($length <= 0) {
            return;
        }

        $offset = $directive->bodyOffset + $from + $start;

        $context->diagnostic->notify(new Diagnostic(
            severity: Severity::Warning,
            message: \sprintf('Extra tokens at the end of #%s directive', $directive->name),
            source: $directive->source,
            offset: $directive->getPhysicalOffset($offset),
            length: $directive->getPhysicalLength($offset, $length),
        ));
    }
}
