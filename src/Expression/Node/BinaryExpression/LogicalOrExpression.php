<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\BinaryExpression;

use FFI\Preprocessor\Expression\Node\BinaryExpression;

/**
 * Logical OR, `a || b`: the right operand is evaluated only in case of the
 * left one is zero.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.5.15 Logical OR operator
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class LogicalOrExpression extends BinaryExpression {}
