<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro;

use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Macro\Executor\MacroExpander;
use FFI\Preprocessor\Macro\MacroContext;

final class ParamExpander
{
    public function __construct(
        /**
         * Replaces the macros in an argument
         */
        private readonly MacroExpander $expander,
    ) {}

    /**
     * @throws MacroInvocationException in case of an invocation in the argument is malformed
     * @throws \Throwable in case of a macro or the tokenizer fails
     */
    public function expand(string $argument, MacroContext $context): string
    {
        return $this->expander->expand($argument, $context);
    }
}
