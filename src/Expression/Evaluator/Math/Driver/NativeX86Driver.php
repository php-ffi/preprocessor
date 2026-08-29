<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\Math\Driver;

use FFI\Preprocessor\Exception\PlatformException;
use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverInterface;

/**
 * Arithmetic on the native integers of a 32-bit PHP without the GMP and the
 * BCMath extensions, a platform that has no arithmetic of `intmax_t`: each
 * operation reports the platform as not supported.
 *
 * @template-implements MathDriverInterface<int>
 */
final class NativeX86Driver implements MathDriverInterface
{
    private static function createError(): PlatformException
    {
        return PlatformException::becausePlatformErrorOccurs(
            '64-bit arithmetic requires a 64-bit version of PHP, as well as the GMP or BCMath extension'
        );
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function createFromDigits(string $digits, int $base): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function createFromInt(int $value): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function isZero(mixed $number): bool
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function isNegative(mixed $number): bool
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function compare(mixed $left, mixed $right, bool $isUnsigned): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function add(mixed $left, mixed $right): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function subtract(mixed $left, mixed $right): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function multiply(mixed $left, mixed $right): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function divide(mixed $left, mixed $right, bool $isUnsigned): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function remainder(mixed $left, mixed $right, bool $isUnsigned): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function negate(mixed $number): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function bitwiseNot(mixed $number): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function bitwiseAnd(mixed $left, mixed $right): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function bitwiseOr(mixed $left, mixed $right): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function bitwiseXor(mixed $left, mixed $right): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function shiftLeft(mixed $number, int $bits): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function shiftRight(mixed $number, int $bits, bool $isUnsigned): int
    {
        throw self::createError();
    }

    /**
     * @throws PlatformException in case of any use
     */
    public function toString(mixed $number, bool $isUnsigned): string
    {
        throw self::createError();
    }
}
