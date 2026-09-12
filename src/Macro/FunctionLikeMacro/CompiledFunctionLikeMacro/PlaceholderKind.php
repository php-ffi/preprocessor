<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro;

/**
 * The way an argument is put into the replacement of a function-like macro (6.10.5.1, 6.10.5.2, 6.10.5.3).
 */
enum PlaceholderKind
{
    /**
     * The argument as written, an operand of `##`
     */
    case Raw;

    /**
     * The argument with the macros in it replaced
     */
    case Expanded;

    /**
     * The argument as a string literal, an operand of `#`
     */
    case Stringified;
}
