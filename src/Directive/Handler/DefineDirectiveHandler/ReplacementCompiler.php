<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler\DefineDirectiveHandler;

use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\ParamExpander;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\ParamStringifier;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\Placeholder;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\PlaceholderKind;
use FFI\Preprocessor\Macro\ObjectLikeMacro;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;

/**
 * Makes a macro of the replacement list of `#define`: finds the operands of `#` and `##` and the parameters
 * among its tokens (6.10.5.1, 6.10.5.2, 6.10.5.3).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Directive
 */
final class ReplacementCompiler
{
    /**
     * The tokens of a replacement list that matter: a literal and a number keep the identifiers inside them
     * from being parameters
     */
    private const TOKEN_PATTERN = '~'
        . '(?<literal>(?:u8|[uUL])?(?:"(?:[^"\\\\\n]|\\\\.)*+"|\'(?:[^\'\\\\\n]|\\\\.)*+\'))'
        . '|(?<number>\.?[0-9](?:[eEpP][+-]|[0-9A-Za-z_\x80-\xFF.]|\'[0-9A-Za-z_])*+)'
        . '|(?<paste>\#\#)'
        . '|(?<stringify>\#)'
        . '|(?<name>[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*+)'
        . '|(?<blank>[ \t\n\r\f\x0B]++)'
        . '|(?<other>.)'
        . '~s';

    private const BLANKS = " \t\n\r\f\x0B";

    public function __construct(
        private readonly ParamExpander $expander,
        private readonly ParamStringifier $stringifier,
    ) {}

    /**
     * @throws InvalidDirectiveBodyException in case of the replacement list has `##` at either end
     */
    public function compileObjectLike(DirectiveChunk $directive, string $replacement): ObjectLikeMacro
    {
        [$template] = $this->readReplacement($directive, $replacement, [], false);

        return new ObjectLikeMacro(\vsprintf($template, []));
    }

    /**
     * @throws InvalidDirectiveBodyException in case of the replacement list has `#` that is not followed by
     *         a parameter or `##` at either end
     */
    public function compileFunctionLike(
        DirectiveChunk $directive,
        string $replacement,
        ParameterList $parameters,
    ): CompiledFunctionLikeMacro {
        [$template, $placeholders] = $this->readReplacement($directive, $replacement, $parameters->names, true);

        return new CompiledFunctionLikeMacro(
            $template,
            $this->expander,
            $this->stringifier,
            $placeholders,
            \count($parameters->names),
            $parameters->isVariadic,
        );
    }

    /**
     * @param list<string> $parameters
     * @return array{string, list<Placeholder>} the `sprintf` format of the replacement and the places in it
     * @throws InvalidDirectiveBodyException in case of the replacement list has `#` that is not followed by
     *         a parameter or `##` at either end
     */
    private function readReplacement(
        DirectiveChunk $directive,
        string $replacement,
        array $parameters,
        bool $isFunctionLike,
    ): array {
        $indexes = \array_flip($parameters);
        $items = [];
        $isStringifying = false;
        $isPasting = false;

        \preg_match_all(self::TOKEN_PATTERN, $replacement, $tokens, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        foreach ($tokens as $token) {
            if ($token['blank'] !== null) {
                if (!$isPasting && !$isStringifying) {
                    $items[] = $token['blank'];
                }

                continue;
            }

            if ($token['paste'] !== null) {
                if ($isStringifying) {
                    throw $this->createStringificationError($directive);
                }

                $items = $this->trimBlanks($items);
                $operand = \array_pop($items);

                if ($operand === null) {
                    throw $this->createConcatenationError($directive);
                }

                $items[] = $this->makeOperandRaw($operand);
                $isPasting = true;

                continue;
            }

            if ($isFunctionLike && $token['stringify'] !== null) {
                if ($isStringifying) {
                    throw $this->createStringificationError($directive);
                }

                $isStringifying = true;

                continue;
            }

            if ($token['name'] !== null && isset($indexes[$token['name']])) {
                $items[] = new Placeholder($indexes[$token['name']], $this->getKind($isStringifying, $isPasting));
                $isStringifying = false;
                $isPasting = false;

                continue;
            }

            if ($isStringifying) {
                throw $this->createStringificationError($directive);
            }

            $items[] = $token[0];
            $isPasting = false;
        }

        if ($isStringifying) {
            throw $this->createStringificationError($directive);
        }

        if ($isPasting) {
            throw $this->createConcatenationError($directive);
        }

        return $this->makeTemplate($items);
    }

    /**
     * @param list<string|Placeholder> $items
     * @return list<string|Placeholder>
     */
    private function trimBlanks(array $items): array
    {
        while ($items !== [] && \is_string($last = \end($items)) && \trim($last, self::BLANKS) === '') {
            \array_pop($items);
        }

        return $items;
    }

    private function makeOperandRaw(string|Placeholder $item): string|Placeholder
    {
        if ($item instanceof Placeholder && $item->kind === PlaceholderKind::Expanded) {
            return new Placeholder($item->index, PlaceholderKind::Raw);
        }

        return $item;
    }

    private function getKind(bool $isStringifying, bool $isPasting): PlaceholderKind
    {
        if ($isStringifying) {
            return PlaceholderKind::Stringified;
        }

        if ($isPasting) {
            return PlaceholderKind::Raw;
        }

        return PlaceholderKind::Expanded;
    }

    /**
     * @param list<string|Placeholder> $items
     * @return array{string, list<Placeholder>}
     */
    private function makeTemplate(array $items): array
    {
        $template = '';
        $placeholders = [];

        foreach ($items as $item) {
            if ($item instanceof Placeholder) {
                $template .= '%s';
                $placeholders[] = $item;

                continue;
            }

            $template .= \str_replace('%', '%%', $item);
        }

        return [$template, $placeholders];
    }

    private function createStringificationError(DirectiveChunk $directive): InvalidDirectiveBodyException
    {
        return InvalidDirectiveBodyException::becauseStringificationHasNoParameter(
            $directive->source,
            $directive->getPhysicalOffset($directive->bodyOffset),
            $directive->name,
        );
    }

    private function createConcatenationError(DirectiveChunk $directive): InvalidDirectiveBodyException
    {
        return InvalidDirectiveBodyException::becauseConcatenationHasNoOperand(
            $directive->source,
            $directive->getPhysicalOffset($directive->bodyOffset),
            $directive->name,
        );
    }
}
