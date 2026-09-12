<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Handler\DefineDirectiveHandler\ParameterList;
use FFI\Preprocessor\Directive\Handler\DefineDirectiveHandler\ReplacementCompiler;
use FFI\Preprocessor\Macro\Executor\MacroExpander;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\ParamExpander;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\ParamStringifier;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#define` defines an object-like or a function-like macro (6.10.5).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
final class DefineDirectiveHandler extends DirectiveHandler
{
    /**
     * A parameter list that starts the rest of a body after the macro name, the parameters are the group
     */
    private const PARAMETERS_PATTERN = '/\A\(([^)]*+)\)/';

    /**
     * An identifier that is a whole text
     */
    private const IDENTIFIER_PATTERN = '/\A[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*+\z/';

    private const PARAMETERS_OPENING = '(';

    private const PARAMETERS_SEPARATOR = ',';

    private const VARIADIC_PARAMETER = '...';

    /**
     * The name of the parameter `...` in a replacement list
     */
    private const VARIADIC_NAME = '__VA_ARGS__';

    /**
     * @throws InvalidDirectiveBodyException in case of the body is not a macro definition
     */
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        $name = $this->getMacroName($directive);
        $rest = \substr($directive->body, \strlen($name));

        $compiler = new ReplacementCompiler(
            new ParamExpander(new MacroExpander($context->macros)),
            new ParamStringifier(),
        );

        if (\preg_match(self::PARAMETERS_PATTERN, $rest, $matches) === 1) {
            $parameters = $this->getParameterList($directive, $matches[1]);
            $replacement = \trim(\substr($rest, \strlen($matches[0])), self::BLANKS);

            $context->macros->defineByMacro($name, $compiler->compileFunctionLike($directive, $replacement, $parameters));

            return [];
        }

        if (\str_starts_with($rest, self::PARAMETERS_OPENING)) {
            throw $this->createParameterListError($directive);
        }

        $context->macros->defineByMacro($name, $compiler->compileObjectLike($directive, \trim($rest, self::BLANKS)));

        return [];
    }

    /**
     * @throws InvalidDirectiveBodyException in case of a parameter is not an identifier or is repeated,
     *         or `...` is not the last one
     */
    private function getParameterList(SourceDirective $directive, string $list): ParameterList
    {
        if (\trim($list, self::BLANKS) === '') {
            return new ParameterList([], false);
        }

        $items = \explode(self::PARAMETERS_SEPARATOR, $list);
        $last = \array_key_last($items);
        $names = [];
        $isVariadic = false;

        foreach ($items as $position => $item) {
            $item = \trim($item, self::BLANKS);

            if ($item === self::VARIADIC_PARAMETER && $position === $last) {
                $names[] = self::VARIADIC_NAME;
                $isVariadic = true;

                continue;
            }

            if (\preg_match(self::IDENTIFIER_PATTERN, $item) !== 1 || \in_array($item, $names, true)) {
                throw $this->createParameterListError($directive);
            }

            $names[] = $item;
        }

        return new ParameterList($names, $isVariadic);
    }

    private function createParameterListError(SourceDirective $directive): InvalidDirectiveBodyException
    {
        return InvalidDirectiveBodyException::becauseParameterListIsInvalid(
            $directive->source,
            $directive->getPhysicalOffset($directive->bodyOffset),
            $directive->name,
        );
    }
}
