<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro;

use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Macro\Executor\MacroExpander;

final class ParamExpander
{
    public function __construct(
        /**
         * Replaces the macros in an argument
         */
        private readonly MacroExpander $expander,
    ) {}

    /**
     * @throws MacroInvocationException
     */
    public function expand(string $argument): string
    {
        return $this->expander->expand($argument);
    }
}
