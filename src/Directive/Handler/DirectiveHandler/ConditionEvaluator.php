<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler\DirectiveHandler;

use FFI\Preprocessor\Directive\Exception\InvalidConditionException;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Expression\Evaluator\ExpressionEvaluatorInterface;
use FFI\Preprocessor\Expression\Exception\ExpressionException;
use FFI\Preprocessor\Expression\Parser\ExpressionParserInterface;
use FFI\Preprocessor\FileSystem\FileSystemInterface;
use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Macro\Executor\MacroExpander;
use FFI\Preprocessor\Macro\MacroRepositoryInterface;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
use Phplrt\Source\StringSource;

/**
 * Evaluates the controlling expression of `#if` and `#elif` to a truth value, with the `defined` and
 * `__has_include` operators (6.10.2p5, 6.10.2p9, 6.10.2p13).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Directive
 */
final class ConditionEvaluator
{
    private const IDENTIFIER = '[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*+';

    /**
     * What is not a part of an identifier
     */
    private const BOUNDARY = '(?<![0-9A-Za-z_\x80-\xFF])';

    /**
     * The `defined` operator, `defined X` or `defined(X)`: the name is the group
     */
    private const DEFINED_PATTERN = '~' . self::BOUNDARY . 'defined(?![0-9A-Za-z_\x80-\xFF])\s*+'
        . '(?:\(\s*+(?<parenthesized>' . self::IDENTIFIER . ')\s*+\)|(?<bare>' . self::IDENTIFIER . '))~';

    /**
     * The `__has_include` operator with a header name: the name is the group
     */
    private const HAS_INCLUDE_PATTERN = '~' . self::BOUNDARY . '__has_include(?![0-9A-Za-z_\x80-\xFF])\s*+'
        . '\(\s*+(?:"(?<quoted>[^"]++)"|<(?<angled>[^>]++)>)\s*+\)~';

    /**
     * A token of the expression that is left after the replacement of the macros: a character constant,
     * a number or an identifier
     */
    private const TOKEN_PATTERN = '~'
        . '(?<literal>(?:u8|[uUL])?\'(?:[^\'\\\\\n]|\\\\.)*+\'?)'
        . '|(?<number>\.?[0-9](?:[eEpP][+-]|[0-9A-Za-z_\x80-\xFF.]|\'[0-9A-Za-z_])*+)'
        . '|(?<identifier>' . self::IDENTIFIER . ')'
        . '~s';

    /**
     * The identifier that is `1` in a controlling expression, the other ones are `0`
     */
    private const TRUE_NAME = 'true';

    public function __construct(
        private readonly MacroRepositoryInterface $macros,
        private readonly MacroExpander $expander,
        private readonly FileSystemInterface $files,
        private readonly ExpressionParserInterface $parser,
        private readonly ExpressionEvaluatorInterface $evaluator,
    ) {}

    /**
     * Whether the expression in the body of a directive is not zero.
     *
     * @throws InvalidDirectiveBodyException in case of the `defined` operator has no identifier
     * @throws MacroInvocationException in case of an invocation of a macro in the expression is malformed
     * @throws InvalidConditionException in case of the expression is not valid or has no valid value
     * @throws InternalException in case of the expression has a node of an unknown kind
     */
    public function evaluate(DirectiveChunk $directive): bool
    {
        $text = $this->replaceDefined($directive->body);
        $text = $this->replaceHasInclude($text, $directive);

        if (\preg_match('~' . self::BOUNDARY . 'defined(?![0-9A-Za-z_\x80-\xFF])~', $text) === 1) {
            throw InvalidDirectiveBodyException::becauseDefinedOperatorIsInvalid(
                $directive->source,
                $directive->getPhysicalOffset($directive->bodyOffset),
                $directive->name,
            );
        }

        $text = $this->replaceIdentifiers($this->expander->expand($text));
        $source = new StringSource($text);

        try {
            return $this->evaluator->evaluate($this->parser->parse($source), $source)->isTrue;
        } catch (ExpressionException $e) {
            throw InvalidConditionException::becauseExpressionIsInvalid($directive, $e);
        }
    }

    private function replaceDefined(string $text): string
    {
        return (string) \preg_replace_callback(
            self::DEFINED_PATTERN,
            fn(array $match): string => $this->toNumber($this->macros->find($match['bare'] ?? $match['parenthesized']) !== null),
            $text,
        );
    }

    private function replaceHasInclude(string $text, DirectiveChunk $directive): string
    {
        return (string) \preg_replace_callback(
            self::HAS_INCLUDE_PATTERN,
            fn(array $match): string => $this->toNumber($this->hasFile($match, $directive)),
            $text,
        );
    }

    /**
     * @param array<array-key, string> $match
     */
    private function hasFile(array $match, DirectiveChunk $directive): bool
    {
        if (isset($match['angled']) && $match['angled'] !== '') {
            return $this->files->find($match['angled']) !== null;
        }

        return $this->files->find($match['quoted'], $directive->source) !== null
            || $this->files->find($match['quoted']) !== null;
    }

    private function replaceIdentifiers(string $text): string
    {
        return (string) \preg_replace_callback(
            self::TOKEN_PATTERN,
            fn(array $match): string => $this->replaceToken($match),
            $text,
        );
    }

    /**
     * @param array<array-key, string> $match
     */
    private function replaceToken(array $match): string
    {
        if (!isset($match['identifier']) || $match['identifier'] === '') {
            return $match[0];
        }

        return $this->toNumber($match['identifier'] === self::TRUE_NAME);
    }

    private function toNumber(bool $value): string
    {
        if ($value) {
            return '1';
        }

        return '0';
    }
}
