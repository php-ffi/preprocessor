<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Executor;

use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Macro\Executor\MacroExpander\CompiledTokenLexer;
use FFI\Preprocessor\Macro\Executor\MacroExpander\Expansion;
use FFI\Preprocessor\Macro\FunctionLikeMacro;
use FFI\Preprocessor\Macro\MacroContext;
use FFI\Preprocessor\Macro\MacroInterface;
use FFI\Preprocessor\Macro\MacroRepositoryInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Lexer\Token\EndOfInputToken;
use Phplrt\Source\StringSource;

/**
 * Replaces the invocations of macros in a text by their replacements, and
 * rescans the result for more (6.10.5). A macro gets the arguments as written.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
final class MacroExpander
{
    private const BLANKS = " \t\n\r\f\x0B";

    private const OPENING = '(';

    private const CLOSING = ')';

    private const SEPARATOR = ',';

    public function __construct(
        /**
         * The macros the text is expanded by
         */
        private readonly MacroRepositoryInterface $macros,
        private readonly CompiledTokenLexer $lexer = new CompiledTokenLexer(),
    ) {}

    /**
     * Returns the text with every macro invocation replaced.
     *
     * @param MacroContext|\Closure(int<0, max>): MacroContext $location the place the text is at, or the place of
     *        the text at an offset of it when the text spans several lines
     * @throws MacroInvocationException in case of an invocation is malformed
     * @throws \Throwable in case of a macro or the tokenizer fails
     */
    public function expand(string $text, MacroContext|\Closure $location): string
    {
        return $this->expandTokens(new Expansion($this->readTokens($text)), $location);
    }

    /**
     * @throws MacroInvocationException in case of an invocation is malformed
     * @throws \Throwable in case of a macro or the tokenizer fails
     */
    private function expandTokens(Expansion $expansion, MacroContext|\Closure $location): string
    {
        $output = '';
        $context = null;

        while (($token = $expansion->pop()) !== null) {
            $macro = $this->findMacro($token, $expansion);

            if ($macro === null) {
                $output .= $token->value;

                continue;
            }

            if ($context === null || $expansion->getDisabled() === []) {
                $context = $this->locate($location, $token);
            }

            if (!$macro instanceof FunctionLikeMacro) {
                $expansion->enter($token->value, $this->readTokens($macro->replace([], $context)));

                continue;
            }

            $arguments = $this->findArguments($token->value, $macro, $expansion);

            if ($arguments === null) {
                $output .= $token->value;

                continue;
            }

            $expansion->enter($token->value, $this->readTokens($macro->replace($arguments, $context)));
        }

        return $output;
    }

    /**
     * @param MacroContext|\Closure(int<0, max>): MacroContext $location
     */
    private function locate(MacroContext|\Closure $location, TokenInterface $token): MacroContext
    {
        if ($location instanceof MacroContext) {
            return $location;
        }

        return $location($token->offset);
    }

    private function findMacro(TokenInterface $token, Expansion $expansion): ?MacroInterface
    {
        if ($token->id !== CompiledTokenLexer::T_IDENTIFIER || $expansion->isDisabled($token->value)) {
            return null;
        }

        return $this->macros->find($token->value);
    }

    /**
     * Reads the arguments of an invocation that follow the macro name,
     * returns {@see null} in case of the name is not followed by `(`.
     *
     * @return list<string>|null the arguments as written, the variadic ones as a single one
     * @throws MacroInvocationException in case of the argument list is not closed or has a wrong number of arguments
     */
    private function findArguments(string $name, FunctionLikeMacro $macro, Expansion $expansion): ?array
    {
        $skipped = [];

        $next = $expansion->pop();

        while ($next !== null && $next->id === CompiledTokenLexer::T_WHITESPACE) {
            $skipped[] = $next;
            $next = $expansion->pop();
        }

        if ($next === null || $next->value !== self::OPENING) {
            if ($next !== null) {
                $skipped[] = $next;
            }

            $expansion->unshift($skipped);

            return null;
        }

        return $this->normalizeArguments($name, $macro, $this->readArguments($name, $expansion));
    }

    /**
     * @return list<string> the arguments as written, up to the closing parenthesis
     * @throws MacroInvocationException in case of the argument list is not closed
     */
    private function readArguments(string $name, Expansion $expansion): array
    {
        $arguments = [];
        $current = '';
        $depth = 0;

        while (($next = $expansion->pop()) !== null) {
            if ($next->value === self::OPENING) {
                ++$depth;
            }

            if ($next->value === self::CLOSING && $depth === 0) {
                $arguments[] = $current;

                return $arguments;
            }

            if ($next->value === self::CLOSING) {
                --$depth;
            }

            if ($next->value === self::SEPARATOR && $depth === 0) {
                $arguments[] = $current;
                $current = '';

                continue;
            }

            $current .= $next->value;
        }

        throw MacroInvocationException::becauseArgumentListIsUnterminated($name);
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     * @throws MacroInvocationException in case of the number of the arguments is wrong
     */
    private function normalizeArguments(string $name, FunctionLikeMacro $macro, array $arguments): array
    {
        $named = $macro->params;

        if ($macro->isVariadic && $named > 0) {
            --$named;
        }

        if (\count($arguments) === 1 && \trim($arguments[0], self::BLANKS) === '' && $named === 0) {
            $arguments = [];
        }

        $given = \count($arguments);

        if (!$macro->isVariadic && $given !== $named) {
            throw MacroInvocationException::becauseArgumentCountIsWrong($name, $named, $given);
        }

        if ($given < $named) {
            throw MacroInvocationException::becauseArgumentsAreTooFew($name, $named, $given);
        }

        $result = \array_slice($arguments, 0, $named);

        if ($macro->isVariadic) {
            $result[] = \implode(self::SEPARATOR, \array_slice($arguments, $named));
        }

        return \array_map(static fn(string $argument): string => \trim($argument, self::BLANKS), $result);
    }

    /**
     * @return list<TokenInterface>
     * @throws \Throwable in case of the tokenizer fails
     */
    private function readTokens(string $text): array
    {
        $tokens = [];

        foreach ($this->lexer->lex(new StringSource($text)) as $token) {
            if (!$token instanceof EndOfInputToken) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }
}
