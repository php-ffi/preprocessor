<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\ObjectLikeMacro;

use FFI\Preprocessor\Macro\MacroContext;
use FFI\Preprocessor\Macro\ObjectLikeMacro;

/**
 * Object-like macro whose replacement depends on the place it is replaced at,
 * such as `__FILE__` and `__LINE__` (6.10.10.2).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.10.2 Environment macros
 */
final class ContextObjectLikeMacro extends ObjectLikeMacro
{
    public function __construct(
        /**
         * Returns the text the name is replaced by
         *
         * @var \Closure(MacroContext): string
         */
        private readonly \Closure $body,
    ) {}

    public function replace(array $arguments, MacroContext $context): string
    {
        return ($this->body)($context);
    }
}
