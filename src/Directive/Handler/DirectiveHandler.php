<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Handler\DirectiveHandler\HeaderName;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Base of the handlers that read the body of a directive.
 */
abstract class DirectiveHandler implements DirectiveHandlerInterface
{
    /**
     * The blanks that may follow the body of a directive.
     */
    protected const BLANKS = " \t\f\x0B";

    /**
     * An identifier at the start of a body
     */
    private const NAME_PATTERN = '/\A[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*+/';

    /**
     * A header name at the start of a body
     */
    private const HEADER_NAME_PATTERN = '/\A(?:"([^"]++)"|<([^>]++)>)/';

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
     * Returns the header name the body of a directive starts with.
     *
     * @throws InvalidDirectiveBodyException in case of the body does not start with a header name
     */
    protected function getHeaderName(SourceDirective $directive): HeaderName
    {
        if (\preg_match(self::HEADER_NAME_PATTERN, $directive->body, $matches) !== 1) {
            throw InvalidDirectiveBodyException::becauseHeaderNameIsInvalid(
                $directive->source,
                $directive->getPhysicalOffset($directive->bodyOffset),
                $directive->name,
            );
        }

        if (isset($matches[2])) {
            return new HeaderName($matches[2], true, \strlen($matches[0]));
        }

        return new HeaderName($matches[1], false, \strlen($matches[0]));
    }

    /**
     * Finds the file of a header name: a quoted name in the directory of the
     * including file first, then from the root (6.10.3p3).
     */
    protected function findFile(DirectiveContext $context, SourceDirective $directive, HeaderName $name): ?FileInterface
    {
        if ($name->isAngled) {
            return $context->files->find($name->pathname);
        }

        return $context->files->find($name->pathname, $directive->source)
            ?? $context->files->find($name->pathname);
    }

    /**
     * Reports the error of a file that is not found.
     *
     * @throws \Throwable in case of the diagnostic listener stops the preprocessing
     */
    protected function reportMissingFile(DirectiveContext $context, SourceDirective $directive, HeaderName $name): void
    {
        $context->diagnostic->notify(new Diagnostic(
            severity: Severity::Error,
            message: \sprintf('#%s: file "%s" is not found', $directive->name, $name->pathname),
            source: $directive->source,
            offset: $directive->getPhysicalOffset($directive->bodyOffset),
            length: $directive->getPhysicalLength($directive->bodyOffset, $name->length),
        ));
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
