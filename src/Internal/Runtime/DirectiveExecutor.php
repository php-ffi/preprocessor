<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Runtime;

use FFI\Contracts\Preprocessor\Directive\DirectiveInterface;
use FFI\Contracts\Preprocessor\Directive\FunctionLikeDirectiveInterface;
use FFI\Contracts\Preprocessor\Directive\RepositoryInterface;
use FFI\Preprocessor\Exception\DirectiveEvaluationException;
use FFI\Preprocessor\Exception\PreprocessException;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Lexer\Token\Token;
use Phplrt\Source\File;

/**
 * @internal directiveExecutor is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Internal\Runtime
 *
 * @psalm-type DirectiveExecutorContext = DirectiveExecutor::CTX_*
 */
final class DirectiveExecutor
{
    /**
     * Source body context.
     *
     * @var DirectiveExecutorContext
     */
    public const CTX_SOURCE = 0x00;

    /**
     * Directive expression body context.
     *
     * @var DirectiveExecutorContext
     */
    public const CTX_EXPRESSION = 0x01;

    /**
     * @var string
     */
    private const ERROR_DIRECTIVE_NOT_FOUND = 'The "%s" directive was not registered';

    /**
     * @var string
     */
    private const PCRE_DEFINED = '/\bdefined\h*(?:\(\h*(\w+)\h*\)|(\w+))/ium';

    private RepositoryInterface $directives;

    public function __construct(RepositoryInterface $directives)
    {
        $this->directives = $directives;
    }

    /**
     * Executes all directives in passed body and returns the result
     * of all replacements.
     *
     * The second argument is responsible for the execution context.
     * Substitutions can be performed both in the body of the source code
     * and in directive expressions.
     *
     * @param DirectiveExecutorContext $ctx
     *
     * @throws DirectiveEvaluationException
     */
    public function replace(string $body, int $ctx = self::CTX_SOURCE): string
    {
        if ($ctx === self::CTX_EXPRESSION) {
            // Replace "defined(X)" expression
            $body = $this->replaceDefinedExpression($body);
        }

        return $this->replaceDefinedDirectives($body);
    }

    /**
     * Replaces all occurrences of "defined(xxx)" with the result of their
     * execution.
     *
     * Such replacements can only be found inside directive expressions.
     *
     * <code>
     *  // before
     *  #if defined(example)
     *  #if defined(__FILE__)
     *
     *  // after
     *  #if false
     *  #if true
     * </code>
     */
    private function replaceDefinedExpression(string $body): string
    {
        $lookup = fn(array $matches): string =>
            /** @psalm-suppress MixedArgument */
            $this->directives->defined($matches[1]) ? 'true' : 'false'
        ;

        return \preg_replace_callback(self::PCRE_DEFINED, $lookup, $body);
    }

    /**
     * Replaces all declared directives with the result of their execution.
     *
     * @psalm-suppress MixedInferredReturnType
     */
    private function replaceDefinedDirectives(string $body): string
    {
        $stream = $this->findAndReplace($body);

        while ($stream->valid()) {
            try {
                /** @psalm-suppress MixedArgument */
                $stream->send($this->execute($stream->key(), $stream->current()));
            } catch (\Throwable $e) {
                $stream->throw($e);
            }
        }

        /** @psalm-suppress MixedReturnStatement */
        return $stream->getReturn();
    }

    /**
     * Applies substitution rules for every registered directive
     * in passed body argument.
     *
     * @see DirectiveExecutor::findDirectiveAndReplace()
     */
    private function findAndReplace(string $body): \Generator
    {
        /**
         * @var string $name
         * @var DirectiveInterface $directive
         */
        foreach ($this->directives as $name => $directive) {
            $stream = $this->findDirectiveAndReplace($name, $directive, $body);

            try {
                yield from $stream;

                $body = (string) $stream->getReturn();
            } catch (\Throwable $e) {
                $stream->throw($e);
            }
        }

        return $body;
    }

    /**
     * Function for runtime replacements of a specific directive:
     *
     * <code>
     * $body = 'ExampleDirective(1, 2)';
     *
     * $replacements = $this->findDirectiveAndReplace('ExampleDirective', ..., $body);
     *
     * while ($replacements->valid()) {
     *      // $name = 'ExampleDirective';
     *      // $arguments = [1, 2];
     *      [$name, $arguments] = [$replacements->key(), $replacements->current()];
     *
     *      $replacements->send('result of ' . \implode(' and ', $arguments));
     * }
     *
     * $replacements->getReturn(); // string(17) "result of 1 and 2"
     * </code>
     *
     * @psalm-return \Generator<string, array, string|null, string>
     */
    private function findDirectiveAndReplace(string $name, DirectiveInterface $directive, string $body): \Generator
    {
        // /
        // This boolean variable includes preprocessor optimizations
        // and means that do not need to do a lookahead to read
        // additional directive arguments.
        //
        $isSimpleDirective = !$directive instanceof FunctionLikeDirectiveInterface
            || $directive->getMaxArgumentsCount() === 0;

        $coroutine = $this->findDirectiveAndUpdateBody($name, $body);

        while ($coroutine->valid()) {
            // Start and End offsets for substitutions
            [$from, $to] = [$coroutine->key(), $coroutine->current()];

            try {
                // Returns the name of the found directive and its arguments.
                // Back it MAY accept a string to replace the found entry.
                $arguments = $isSimpleDirective ? [] : $this->extractArguments($body, $to);

                if (!$isSimpleDirective && $arguments === []) {
                    // Workaround for a case when macro functions are not used
                    // as functions. In such cases, all substitutions should be
                    // ignored.
                    $coroutine->next();
                    continue;
                }

                $replacement = yield $name => $arguments;
            } catch (\Throwable $e) {
                $token = $this->createTokenForSource($name, $body, $from, $to);

                throw PreprocessException::fromSource($e->getMessage(), File::fromSources($body), $token);
            }

            // In the case that replacement is not required, then we move
            // on to the next detected directive.
            if (!\is_string($replacement)) {
                $coroutine->next();
                continue;
            }

            // Otherwise, we update the body in the replacement stream for
            // the specified directive.
            $prefix = \substr($body, 0, $from);
            $suffix = \substr($body, $to);

            $coroutine->send($body = $prefix . $replacement . $suffix);
        }

        return $coroutine->getReturn();
    }

    /**
     * Finds all occurrences of directive name in the body and their offsets.
     *
     * If a new string value is passed to the generator, then the processed
     * body will be updated with this new value.
     *
     * <code>
     *  $stream = $this->findDirective('example', $body);
     *
     *  while ($stream->valid()) {
     *      $offset = $stream->current();
     *      // Do replace define "example" at offset "$offset"
     *      $stream->send($newBody);
     *  }
     * </code>
     *
     * @psalm-return \Generator<int, int, string, string>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    private function findDirectiveAndUpdateBody(string $name, string $body): \Generator
    {
        [$length, $offset] = [\strlen($name), 0];

        while (isset($body[$offset])) {
            $offset = @\strpos($body, $name, $offset);

            if (!\is_int($offset)) {
                break;
            }

            if ($this->isPartOfName($offset, $length, $body)) {
                ++$offset;
                continue;
            }

            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if (\is_string($replacement = yield $offset => $offset + $length)) {
                $body = $replacement;
            }

            ++$offset;
        }

        return $body;
    }

    /**
     * Returns {@see true} if the directive in the specified offset is part
     * of another name or {@see false} instead. For example:
     *
     * <code>
     * $this->isPartOfName(0, 3, 'abcd');  // true ("abc" is part of "abcd")
     * $this->isPartOfName(0, 3, 'abc()'); // false ("abc" is a full name)
     * </code>
     */
    private function isPartOfName(int $offset, int $length, string $body): bool
    {
        $startsWithNameChar = $offset !== 0 && $this->isAvailableInNames($body[$offset - 1]);

        return
            // When starts with [_a-z0-9]
            $startsWithNameChar
            // Or ends with [_a-z0-9]
            || $this->isAvailableInNames($body[$offset + $length] ?? '');
    }

    /**
     * Returns {@see true} if the char is valid when used in function
     * and variable names or {@see false} otherwise.
     */
    private function isAvailableInNames(string $char): bool
    {
        return $char === '_' || \ctype_alnum($char);
    }

    /**
     * Method for reading arguments that should be located after the
     * specified offset.
     *
     * Note that the method returns a new offset by reference.
     */
    private function extractArguments(string $body, int &$offset): array
    {
        $initial = $offset;

        // Skip all whitespaces
        while (\ctype_space($body[$offset] ?? '')) {
            ++$offset;
        }

        // If there is no "(" parenthesis after the whitespace characters,
        // then no further search should be performed, since the arguments
        // for the specified directive were not passed.
        if (($body[$offset] ?? '') !== '(') {
            $offset = $initial;

            return [];
        }

        [$arguments, $buffer, $depth] = [[], '', 0];

        do {
            // Current character in body.
            $current = $body[$offset++] ?? '';

            switch ($current) {
                // In the case that the current offset has exceeded the
                // allowable size, then we consider that no arguments were
                // passed.
                //
                // This situation can arise if the closing parenthesis is
                // missing in the source code.
                case '':
                    $offset = $initial;

                    return [];

                    // To count the same number of open and close parentheses.
                    //
                    // In the case that the opening parenthesis "(" is part of the
                    // argument (depth > 0), then add it in the buffer.
                case '(':
                    if ($depth !== 0) {
                        $buffer .= $current;
                    }
                    ++$depth;
                    break;

                    // To count the same number of open and close parentheses.
                    //
                    // In the case that this is the last close parenthesis, then
                    // return all arguments or add ")" in the buffer otherwise.
                case ')':
                    $depth--;

                    if ($depth === 0) {
                        return [...$arguments, \trim($buffer)];
                    }

                    $buffer .= $current;
                    break;

                    // Directive arguments separator.
                    //
                    // If the current nesting level does not exceed one, it
                    // creates a new argument from the current buffer.
                case ',':
                    if ($depth === 1) {
                        $arguments[] = \trim($buffer);
                        $buffer = '';
                    } else {
                        $buffer .= $current;
                    }
                    break;

                    // All other characters are simply added to the
                    // current buffer.
                default:
                    $buffer .= $current;
            }
        } while ($depth !== 0);

        return $arguments;
    }

    private function createTokenForSource(string $name, string $body, int $from, int $to): TokenInterface
    {
        $slice = \substr($body, $from, $to - $from);

        return new Token($name, $slice, $from);
    }

    /**
     * Accepts the name of a directive and its arguments, and returns the
     * result of executing that directive.
     *
     * @param non-empty-string $name
     *
     * @throws DirectiveEvaluationException
     */
    public function execute(string $name, array $arguments = []): string
    {
        $directive = $this->directives->find($name);

        if ($directive === null) {
            if ($arguments === []) {
                return $name;
            }

            throw new DirectiveEvaluationException(\sprintf(self::ERROR_DIRECTIVE_NOT_FOUND, $name));
        }

        try {
            return $directive(...$arguments);
        } catch (DirectiveEvaluationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DirectiveEvaluationException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
