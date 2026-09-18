<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro;

/**
 * Macro with a parameter list, `#define max(a, b) ((a) > (b) ? (a) : (b))`:
 * its name followed by `(` is replaced by the text made of the arguments
 * (6.10.5p10).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
abstract class FunctionLikeMacro implements MacroInterface
{
    public function __construct(
        /**
         * The number of the parameters, the variadic one included
         *
         * @var int<0, max>
         */
        public readonly int $params,
        /**
         * Whether the last parameter is `...` and takes the rest of the arguments
         */
        public readonly bool $isVariadic = false,
    ) {}
}
