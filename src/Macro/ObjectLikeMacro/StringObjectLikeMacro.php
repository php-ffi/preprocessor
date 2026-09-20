<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\ObjectLikeMacro;

use FFI\Preprocessor\Macro\MacroContext;
use FFI\Preprocessor\Macro\ObjectLikeMacro;

/**
 * Object-like macro whose replacement list is a ready text.
 */
final class StringObjectLikeMacro extends ObjectLikeMacro
{
    public function __construct(
        /**
         * The text the name is replaced by
         */
        public readonly string $replacement = '',
    ) {}

    public function replace(array $arguments, MacroContext $context): string
    {
        return $this->replacement;
    }
}
