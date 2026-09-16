<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\Math\Driver;

use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverInterface;

/**
 * Arithmetic on the native integers of a 64-bit PHP: a number is the PHP
 * integer of the same 64 bits, every operation wraps around modulo 2^64.
 *
 * @template-implements MathDriverInterface<int>
 */
final class NativeX64Driver implements MathDriverInterface
{
    /**
     * The lowest 32 bits
     */
    private const LOW_32 = 0xFFFF_FFFF;

    /**
     * The lowest 16 bits
     */
    private const LOW_16 = 0xFFFF;

    public function createFromDigits(string $digits, int $base): int
    {
        $bits = match ($base) {
            2 => 1,
            8 => 3,
            16 => 4,
            default => 0,
        };

        $number = 0;

        foreach (\str_split($digits) as $digit) {
            $value = (int) \hexdec($digit);

            $number = $bits === 0
                ? $this->add($this->multiply($number, 10), $value)
                : ($number << $bits) | $value;
        }

        return $number;
    }

    public function createFromInt(int $value): int
    {
        return $value;
    }

    public function isZero(mixed $number): bool
    {
        return $number === 0;
    }

    public function isNegative(mixed $number): bool
    {
        return $number < 0;
    }

    public function compare(mixed $left, mixed $right, bool $isUnsigned): int
    {
        if ($isUnsigned) {
            // Unsigned comparison
            return ($left ^ \PHP_INT_MIN) <=> ($right ^ \PHP_INT_MIN);
        }

        return $left <=> $right;
    }

    public function add(mixed $left, mixed $right): int
    {
        $low = ($left & self::LOW_32) + ($right & self::LOW_32);
        $high = (($left >> 32) & self::LOW_32) + (($right >> 32) & self::LOW_32) + ($low >> 32);

        return (($high & self::LOW_32) << 32) | ($low & self::LOW_32);
    }

    public function subtract(mixed $left, mixed $right): int
    {
        return $this->add($left, $this->negate($right));
    }

    public function multiply(mixed $left, mixed $right): int
    {
        // Multiplication modulo 2^64 by the parts of 16 bits
        $a = [$left & self::LOW_16, ($left >> 16) & self::LOW_16, ($left >> 32) & self::LOW_16, ($left >> 48) & self::LOW_16];
        $b = [$right & self::LOW_16, ($right >> 16) & self::LOW_16, ($right >> 32) & self::LOW_16, ($right >> 48) & self::LOW_16];
        $result = 0;

        for ($i = 0; $i < 4; ++$i) {
            for ($j = 0; $i + $j < 4; ++$j) {
                $result = $this->add($result, ($a[$i] * $b[$j]) << (16 * ($i + $j)));
            }
        }

        return $result;
    }

    public function divide(mixed $left, mixed $right, bool $isUnsigned): int
    {
        if (!$isUnsigned || ($left >= 0 && $right >= 0)) {
            return \intdiv($left, $right);
        }

        return $this->divideUnsigned($left, $right)[0];
    }

    public function remainder(mixed $left, mixed $right, bool $isUnsigned): int
    {
        if (!$isUnsigned || ($left >= 0 && $right >= 0)) {
            return $left % $right;
        }

        return $this->divideUnsigned($left, $right)[1];
    }

    /**
     * Divides the numbers as unsigned ones, one of them has the highest bit
     * set.
     *
     * @return array{int, int} the quotient and the remainder
     */
    private function divideUnsigned(int $left, int $right): array
    {
        // Unsigned division by a divisor with the highest bit set
        if ($right < 0) {
            return $this->compare($left, $right, true) >= 0 ? [1, $this->subtract($left, $right)] : [0, $left];
        }

        // Unsigned division of a dividend with the highest bit set
        $quotient = \intdiv(($left >> 1) & \PHP_INT_MAX, $right) << 1;
        $rest = $this->subtract($left, $this->multiply($quotient, $right));

        if ($this->compare($rest, $right, true) >= 0) {
            ++$quotient;
            $rest = $this->subtract($rest, $right);
        }

        return [$quotient, $rest];
    }

    public function negate(mixed $number): int
    {
        return $this->add(~$number, 1);
    }

    public function bitwiseNot(mixed $number): int
    {
        return ~$number;
    }

    public function bitwiseAnd(mixed $left, mixed $right): int
    {
        return $left & $right;
    }

    public function bitwiseOr(mixed $left, mixed $right): int
    {
        return $left | $right;
    }

    public function bitwiseXor(mixed $left, mixed $right): int
    {
        return $left ^ $right;
    }

    public function shiftLeft(mixed $number, int $bits): int
    {
        return $number << $bits;
    }

    public function shiftRight(mixed $number, int $bits, bool $isUnsigned): int
    {
        if (!$isUnsigned || $bits === 0) {
            return $number >> $bits;
        }

        return ($number >> $bits) & (\PHP_INT_MAX >> ($bits - 1));
    }

    public function toString(mixed $number, bool $isUnsigned): string
    {
        return $isUnsigned ? \sprintf('%u', $number) : (string) $number;
    }
}
