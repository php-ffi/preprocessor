<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\UnaryExpression;

use FFI\Preprocessor\Expression\Node\UnaryExpression;

/**
 * Bitwise complement, `~x`.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.5.4.3 Unary arithmetic operators
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class BitwiseNotExpression extends UnaryExpression {}
