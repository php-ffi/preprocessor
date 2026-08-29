<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\Math\Driver;

use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverInterface;

/**
 * Arithmetic on the integers of the GMP extension: a number is the unsigned
 * value of the 64 bits, from 0 to 2^64 - 1.
 *
 * @template-implements MathDriverInterface<\GMP>
 */
final class GmpDriver implements MathDriverInterface
{
    /**
     * 2^64, the modulus of the arithmetic
     */
    private readonly \GMP $modulus;

    /**
     * 2^63, the least number with the highest bit set
     */
    private readonly \GMP $half;

    public function __construct()
    {
        $this->modulus = \gmp_pow(2, 64);
        $this->half = \gmp_pow(2, 63);
    }

    public function createFromDigits(string $digits, int $base): \GMP
    {
        return \gmp_init($digits, $base);
    }

    public function createFromInt(int $value): \GMP
    {
        return $this->wrap(\gmp_init($value));
    }

    public function isZero(mixed $number): bool
    {
        return \gmp_sign($number) === 0;
    }

    public function isNegative(mixed $number): bool
    {
        return \gmp_cmp($number, $this->half) >= 0;
    }

    public function compare(mixed $left, mixed $right, bool $isUnsigned): int
    {
        if ($isUnsigned) {
            return \gmp_cmp($left, $right);
        }

        return \gmp_cmp($this->toSigned($left), $this->toSigned($right));
    }

    public function add(mixed $left, mixed $right): \GMP
    {
        return $this->wrap(\gmp_add($left, $right));
    }

    public function subtract(mixed $left, mixed $right): \GMP
    {
        return $this->wrap(\gmp_sub($left, $right));
    }

    public function multiply(mixed $left, mixed $right): \GMP
    {
        return $this->wrap(\gmp_mul($left, $right));
    }

    public function divide(mixed $left, mixed $right, bool $isUnsigned): \GMP
    {
        if ($isUnsigned) {
            return \gmp_div_q($left, $right);
        }

        return $this->wrap(\gmp_div_q($this->toSigned($left), $this->toSigned($right), \GMP_ROUND_ZERO));
    }

    public function remainder(mixed $left, mixed $right, bool $isUnsigned): \GMP
    {
        if ($isUnsigned) {
            return \gmp_div_r($left, $right);
        }

        return $this->wrap(\gmp_div_r($this->toSigned($left), $this->toSigned($right), \GMP_ROUND_ZERO));
    }

    public function negate(mixed $number): \GMP
    {
        return $this->wrap(\gmp_neg($number));
    }

    public function bitwiseNot(mixed $number): \GMP
    {
        return \gmp_sub(\gmp_sub($this->modulus, 1), $number);
    }

    public function bitwiseAnd(mixed $left, mixed $right): \GMP
    {
        return \gmp_and($left, $right);
    }

    public function bitwiseOr(mixed $left, mixed $right): \GMP
    {
        return \gmp_or($left, $right);
    }

    public function bitwiseXor(mixed $left, mixed $right): \GMP
    {
        return \gmp_xor($left, $right);
    }

    public function shiftLeft(mixed $number, int $bits): \GMP
    {
        return $this->wrap(\gmp_mul($number, \gmp_pow(2, $bits)));
    }

    public function shiftRight(mixed $number, int $bits, bool $isUnsigned): \GMP
    {
        if ($isUnsigned) {
            return \gmp_div_q($number, \gmp_pow(2, $bits));
        }

        // The floor of the signed division is the arithmetic shift
        return $this->wrap(\gmp_div_q($this->toSigned($number), \gmp_pow(2, $bits), \GMP_ROUND_MINUSINF));
    }

    public function toString(mixed $number, bool $isUnsigned): string
    {
        /** @var numeric-string */
        return \gmp_strval($isUnsigned ? $number : $this->toSigned($number));
    }

    /**
     * Brings an integer to the range of the 64-bit patterns.
     */
    private function wrap(\GMP $number): \GMP
    {
        return \gmp_mod($number, $this->modulus);
    }

    /**
     * Reads the 64 bits as a signed integer.
     */
    private function toSigned(\GMP $number): \GMP
    {
        return \gmp_cmp($number, $this->half) >= 0 ? \gmp_sub($number, $this->modulus) : $number;
    }
}
