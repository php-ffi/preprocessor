<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler\DefineDirectiveHandler;

/**
 * Parameters of a function-like macro in `#define`, such as `(a, b, ...)` (6.10.5p10).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Directive
 */
final class ParameterList
{
    public function __construct(
        /**
         * The names of the parameters, `__VA_ARGS__` for `...`
         *
         * @var list<string>
         */
        public readonly array $names,
        /**
         * Whether the last parameter is `...`
         */
        public readonly bool $isVariadic,
    ) {}
}
