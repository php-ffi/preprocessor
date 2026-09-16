<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\Math\Driver;

use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverInterface;

/**
 * Arithmetic on the decimal strings of the BCMath extension: a number is the
 * unsigned value of the 64 bits, from 0 to 2^64 - 1, whatever the
 * `bcmath.scale` setting is.
 *
 * @template-implements MathDriverInterface<numeric-string>
 */
final class BcMathDriver implements MathDriverInterface
{
    /**
     * 2^64, the modulus of the arithmetic
     */
    private const MODULUS = '18446744073709551616';

    /**
     * 2^64 - 1, all the bits set
     */
    private const MAX = '18446744073709551615';

    /**
     * 2^63, the least number with the highest bit set
     */
    private const HALF = '9223372036854775808';

    /**
     * 2^16, the base of the parts of a bitwise operation
     */
    private const PART = '65536';

    public function createFromDigits(string $digits, int $base): string
    {
        $number = '0';

        foreach (\str_split($digits) as $digit) {
            $number = \bcadd(\bcmul($number, (string) $base, 0), (string) \hexdec($digit), 0);
        }

        return $number;
    }

    public function createFromInt(int $value): string
    {
        return $this->wrap((string) $value);
    }

    public function isZero(mixed $number): bool
    {
        return \bccomp($number, '0', 0) === 0;
    }

    public function isNegative(mixed $number): bool
    {
        return \bccomp($number, self::HALF, 0) >= 0;
    }

    public function compare(mixed $left, mixed $right, bool $isUnsigned): int
    {
        if ($isUnsigned) {
            return \bccomp($left, $right, 0);
        }

        return \bccomp($this->toSigned($left), $this->toSigned($right), 0);
    }

    public function add(mixed $left, mixed $right): string
    {
        return $this->wrap(\bcadd($left, $right, 0));
    }

    public function subtract(mixed $left, mixed $right): string
    {
        return $this->wrap(\bcsub($left, $right, 0));
    }

    public function multiply(mixed $left, mixed $right): string
    {
        return $this->wrap(\bcmul($left, $right, 0));
    }

    public function divide(mixed $left, mixed $right, bool $isUnsigned): string
    {
        if ($isUnsigned) {
            return \bcdiv($left, $right, 0);
        }

        return $this->wrap(\bcdiv($this->toSigned($left), $this->toSigned($right), 0));
    }

    public function remainder(mixed $left, mixed $right, bool $isUnsigned): string
    {
        if ($isUnsigned) {
            return \bcmod($left, $right, 0);
        }

        return $this->wrap(\bcmod($this->toSigned($left), $this->toSigned($right), 0));
    }

    public function negate(mixed $number): string
    {
        return $this->wrap(\bcsub('0', $number, 0));
    }

    public function bitwiseNot(mixed $number): string
    {
        return \bcsub(self::MAX, $number, 0);
    }

    public function bitwiseAnd(mixed $left, mixed $right): string
    {
        return $this->combine($left, $right, static fn(int $a, int $b): int => $a & $b);
    }

    public function bitwiseOr(mixed $left, mixed $right): string
    {
        return $this->combine($left, $right, static fn(int $a, int $b): int => $a | $b);
    }

    public function bitwiseXor(mixed $left, mixed $right): string
    {
        return $this->combine($left, $right, static fn(int $a, int $b): int => $a ^ $b);
    }

    public function shiftLeft(mixed $number, int $bits): string
    {
        return $this->wrap(\bcmul($number, \bcpow('2', (string) $bits, 0), 0));
    }

    public function shiftRight(mixed $number, int $bits, bool $isUnsigned): string
    {
        $divisor = \bcpow('2', (string) $bits, 0);

        if ($isUnsigned || !$this->isNegative($number)) {
            return \bcdiv($number, $divisor, 0);
        }

        // Arithmetic shift of a negative number
        $signed = $this->toSigned($number);
        $quotient = \bcdiv($signed, $divisor, 0);

        if (\bccomp(\bcmul($quotient, $divisor, 0), $signed, 0) !== 0) {
            $quotient = \bcsub($quotient, '1', 0);
        }

        return $this->wrap($quotient);
    }

    public function toString(mixed $number, bool $isUnsigned): string
    {
        return $isUnsigned ? $number : $this->toSigned($number);
    }

    /**
     * Applies the operation to each part of 16 bits of the numbers.
     *
     * @param numeric-string $left
     * @param numeric-string $right
     * @param \Closure(int, int): int $operation
     * @return numeric-string
     */
    private function combine(string $left, string $right, \Closure $operation): string
    {
        $result = '0';
        $factor = '1';

        for ($i = 0; $i < 4; ++$i) {
            $part = $operation((int) \bcmod($left, self::PART, 0), (int) \bcmod($right, self::PART, 0));
            $result = \bcadd($result, \bcmul((string) $part, $factor, 0), 0);
            $left = \bcdiv($left, self::PART, 0);
            $right = \bcdiv($right, self::PART, 0);
            $factor = \bcmul($factor, self::PART, 0);
        }

        return $result;
    }

    /**
     * Brings an integer to the range of the 64-bit patterns.
     *
     * @param numeric-string $number
     * @return numeric-string
     */
    private function wrap(string $number): string
    {
        $result = \bcmod($number, self::MODULUS, 0);

        return \bccomp($result, '0', 0) < 0 ? \bcadd($result, self::MODULUS, 0) : $result;
    }

    /**
     * Reads the 64 bits as a signed integer.
     *
     * @param numeric-string $number
     * @return numeric-string
     */
    private function toSigned(string $number): string
    {
        return \bccomp($number, self::HALF, 0) >= 0 ? \bcsub($number, self::MODULUS, 0) : $number;
    }
}
