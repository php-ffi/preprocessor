<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro;

/**
 * Macro: what a name is replaced by (6.10.5). The name is given by the
 * repository the macro is defined in.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
interface MacroInterface
{
    /**
     * Returns the text the name, with the arguments if any, is replaced by.
     *
     * @param list<string> $arguments the arguments as written in the invocation
     * @param MacroContext $context the place the name is written at
     * @throws \Throwable in case of the macro fails to make its replacement
     */
    public function replace(array $arguments, MacroContext $context): string;
}
