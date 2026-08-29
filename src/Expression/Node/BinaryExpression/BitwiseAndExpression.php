<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\BinaryExpression;

use FFI\Preprocessor\Expression\Node\BinaryExpression;

/**
 * Bitwise AND, `a & b`.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.5.11 Bitwise AND operator
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class BitwiseAndExpression extends BinaryExpression {}
