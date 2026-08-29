<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\Math;

/**
 * Arithmetic of the 64-bit integers of a conditional directive, `intmax_t`
 * and `uintmax_t` (6.10.2p13). A number is a 64-bit pattern: the operations
 * that do not depend on the signedness wrap around modulo 2^64, the ones
 * that do take it as an argument.
 *
 * @template TNumber of mixed
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 */
interface MathDriverInterface
{
    /**
     * Converts the digits in the base into a number.
     *
     * @param non-empty-string $digits the digits in lowercase, of a value
     *        below 2^64
     * @param 2|8|10|16 $base
     * @return TNumber
     */
    public function createFromDigits(string $digits, int $base): mixed;

    /**
     * Converts the integer into a number, a negative one in two's complement.
     *
     * @return TNumber
     */
    public function createFromInt(int $value): mixed;

    /**
     * @param TNumber $number
     */
    public function isZero(mixed $number): bool;

    /**
     * Whether the highest of the 64 bits is set: the number is negative as
     * a signed one.
     *
     * @param TNumber $number
     */
    public function isNegative(mixed $number): bool;

    /**
     * Compares the numbers, returns a negative integer, zero or a positive
     * integer as the first one is less than, equal to or greater than the
     * second one.
     *
     * @param TNumber $left
     * @param TNumber $right
     */
    public function compare(mixed $left, mixed $right, bool $isUnsigned): int;

    /**
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function add(mixed $left, mixed $right): mixed;

    /**
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function subtract(mixed $left, mixed $right): mixed;

    /**
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function multiply(mixed $left, mixed $right): mixed;

    /**
     * Divides the numbers, the quotient truncated toward zero. The divisor
     * is never zero, a signed division never divides the least value by -1.
     *
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function divide(mixed $left, mixed $right, bool $isUnsigned): mixed;

    /**
     * Returns the remainder of the division, of the sign of the dividend. The
     * divisor is never zero, a signed division never divides the least value
     * by -1.
     *
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function remainder(mixed $left, mixed $right, bool $isUnsigned): mixed;

    /**
     * @param TNumber $number
     * @return TNumber
     */
    public function negate(mixed $number): mixed;

    /**
     * @param TNumber $number
     * @return TNumber
     */
    public function bitwiseNot(mixed $number): mixed;

    /**
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function bitwiseAnd(mixed $left, mixed $right): mixed;

    /**
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function bitwiseOr(mixed $left, mixed $right): mixed;

    /**
     * @param TNumber $left
     * @param TNumber $right
     * @return TNumber
     */
    public function bitwiseXor(mixed $left, mixed $right): mixed;

    /**
     * @param TNumber $number
     * @param int<0, 63> $bits
     * @return TNumber
     */
    public function shiftLeft(mixed $number, int $bits): mixed;

    /**
     * Shifts the number right: the highest bit is copied into the vacated
     * bits of a signed number, zeros fill those of an unsigned one.
     *
     * @param TNumber $number
     * @param int<0, 63> $bits
     * @return TNumber
     */
    public function shiftRight(mixed $number, int $bits, bool $isUnsigned): mixed;

    /**
     * Returns the decimal digits of the number, with a minus of a negative
     * signed one.
     *
     * @param TNumber $number
     * @return numeric-string
     */
    public function toString(mixed $number, bool $isUnsigned): string;
}
