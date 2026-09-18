<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro;

/**
 * Macro without a parameter list, `#define BUFFER_SIZE 4096`: its name is
 * replaced by the replacement list (6.10.5p9).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
abstract class ObjectLikeMacro implements MacroInterface {}
