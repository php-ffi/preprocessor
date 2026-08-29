<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\Literal;

use FFI\Preprocessor\Expression\Node\Literal;
use FFI\Preprocessor\Expression\Node\Literal\IntLiteral\IntTypeSuffix;

/**
 * Integer constant, such as `42`, `0x1F'FFu` or `0b1010ull`. The base it is
 * written in is the class of the node. There is no sign: a minus before a
 * constant is a unary operator.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.2 Integer constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
abstract class IntLiteral extends Literal
{
    /**
     * @param non-empty-string $raw
     * @param int<0, max> $offset
     */
    final public function __construct(
        /**
         * The digits of the value in the base of the constant, without the
         * prefix, the separators and the leading zeros, in lowercase, `0` for
         * zero. The value is not limited by any type.
         *
         * @var non-empty-string
         */
        public readonly string $digits,
        /**
         * The type suffix, {@see null} in case of there is none
         */
        public readonly ?IntTypeSuffix $suffix,
        /**
         * GCC: whether the constant is imaginary, written with the suffix `i`
         * or `j` (`3i`, `3uj`)
         */
        public readonly bool $isImaginary,
        string $raw,
        int $offset = 0,
    ) {
        parent::__construct($raw, $offset);
    }

    /**
     * Returns the base the digits are written in.
     *
     * @return 2|8|10|16
     */
    abstract public static function getBase(): int;
}
