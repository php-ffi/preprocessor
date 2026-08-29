<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\Literal\IntLiteral;

use FFI\Preprocessor\Expression\Node\Literal\IntLiteral;

/**
 * Decimal constant, `42`.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.2 Integer constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class DecIntLiteral extends IntLiteral
{
    public static function getBase(): int
    {
        return 10;
    }
}
