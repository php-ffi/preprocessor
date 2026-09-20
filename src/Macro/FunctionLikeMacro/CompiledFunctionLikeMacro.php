<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro;

use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Macro\FunctionLikeMacro;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\ParamExpander;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\ParamStringifier;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\Placeholder;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro\PlaceholderKind;
use FFI\Preprocessor\Macro\MacroContext;

/**
 * Function-like macro compiled from a `#define`: the text of the replacement with a `sprintf` format
 * in which the Nth `%s` is the place of the Nth placeholder. It takes the arguments as written.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
final class CompiledFunctionLikeMacro extends FunctionLikeMacro
{
    /**
     * @param int<0, max> $params
     */
    public function __construct(
        /**
         * The text of the replacement, a `%` that is not a placeholder, is written `%%`
         */
        public readonly string $template,
        private readonly ParamExpander $expander,
        private readonly ParamStringifier $stringifier,
        /**
         * The places of the arguments in the template, in the order of the `%s`
         *
         * @var list<Placeholder>
         */
        public readonly array $placeholders,
        int $params,
        bool $isVariadic = false,
    ) {
        parent::__construct($params, $isVariadic);
    }

    /**
     * @throws MacroInvocationException in case of an invocation in an argument is malformed
     * @throws \Throwable in case of a macro or the tokenizer fails
     */
    public function replace(array $arguments, MacroContext $context): string
    {
        $values = [];

        foreach ($this->placeholders as $placeholder) {
            $values[] = $this->processArgument($placeholder->kind, $arguments[$placeholder->index] ?? '', $context);
        }

        return \vsprintf($this->template, $values);
    }

    /**
     * @throws MacroInvocationException in case of an invocation in the argument is malformed
     * @throws \Throwable in case of a macro or the tokenizer fails
     */
    private function processArgument(PlaceholderKind $kind, string $argument, MacroContext $context): string
    {
        return match ($kind) {
            PlaceholderKind::Raw => $argument,
            PlaceholderKind::Expanded => $this->expander->expand($argument, $context),
            PlaceholderKind::Stringified => $this->stringifier->stringify($argument),
        };
    }
}
