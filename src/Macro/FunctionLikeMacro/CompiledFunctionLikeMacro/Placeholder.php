<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro;

/**
 * The place of the replacement an argument of the invocation is put to.
 */
final class Placeholder
{
    public function __construct(
        /**
         * The index of the argument
         *
         * @var int<0, max>
         */
        public readonly int $index,
        /**
         * The way the argument is put
         */
        public readonly PlaceholderKind $kind,
    ) {}
}
