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
     * @param list<string> $arguments
     */
    public function replace(array $arguments): string;
}
