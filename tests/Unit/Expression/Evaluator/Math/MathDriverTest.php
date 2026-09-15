<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Expression\Evaluator\Math;

use FFI\Preprocessor\Expression\Evaluator\Math\Driver\BcMathDriver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\GmpDriver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\NativeX64Driver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\NativeX86Driver;
use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverFactory;
use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverInterface;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(NativeX64Driver::class)]
#[CoversClass(GmpDriver::class)]
#[CoversClass(BcMathDriver::class)]
#[CoversClass(NativeX86Driver::class)]
#[CoversClass(MathDriverFactory::class)]
final class MathDriverTest extends TestCase
{
    private const MAX = '18446744073709551615';

    private const SIGNED_MAX = '9223372036854775807';

    private const SIGNED_MIN = '-9223372036854775808';

    /**
     * The drivers the platform supports.
     *
     * @return iterable<non-empty-string, array{MathDriverInterface<mixed>}>
     */
    public static function driverDataProvider(): iterable
    {
        if (\PHP_INT_SIZE >= 8) {
            yield 'native 64-bit' => [new NativeX64Driver()];
        }

        if (\extension_loaded('gmp')) {
            yield 'GMP' => [new GmpDriver()];
        }

        if (\extension_loaded('bcmath')) {
            yield 'BCMath' => [new BcMathDriver()];
        }
    }

    /**
     * Each case gives the expected decimal digits, signed and unsigned, of
     * an operation over the numbers made from decimal digits, a negative one
     * in two's complement.
     *
     * @return iterable<non-empty-string, array{
     *     \Closure(MathDriverInterface<mixed>, \Closure(string): mixed): mixed,
     *     string,
     *     string
     * }>
     */
    private static function operations(): iterable
    {
        $max = self::MAX;
        $signedMax = self::SIGNED_MAX;
        $signedMin = self::SIGNED_MIN;

        yield 'zero' => [static fn($m, $n) => $n('0'), '0', '0'];
        yield 'all bits' => [static fn($m, $n) => $m->createFromDigits('ffffffffffffffff', 16), '-1', $max];
        yield 'decimal maximum' => [static fn($m, $n) => $m->createFromDigits($max, 10), '-1', $max];
        yield 'octal maximum' => [static fn($m, $n) => $m->createFromDigits('1777777777777777777777', 8), '-1', $max];
        yield 'binary' => [static fn($m, $n) => $m->createFromDigits('101', 2), '5', '5'];
        yield 'highest bit' => [static fn($m, $n) => $m->createFromDigits('8000000000000000', 16), $signedMin, '9223372036854775808'];
        yield 'negative integer' => [static fn($m, $n) => $m->createFromInt(-2), '-2', '18446744073709551614'];
        yield 'sum' => [static fn($m, $n) => $m->add($n('40'), $n('2')), '42', '42'];
        yield 'sum overflows into the sign' => [static fn($m, $n) => $m->add($n($signedMax), $n('1')), $signedMin, '9223372036854775808'];
        yield 'sum wraps around' => [static fn($m, $n) => $m->add($n('-1'), $n('1')), '0', '0'];
        yield 'sum of negatives' => [static fn($m, $n) => $m->add($n('-5'), $n('-7')), '-12', '18446744073709551604'];
        yield 'difference below zero' => [static fn($m, $n) => $m->subtract($n('0'), $n('1')), '-1', $max];
        yield 'difference of the least value' => [static fn($m, $n) => $m->subtract($n($signedMin), $n('1')), $signedMax, $signedMax];
        yield 'product' => [static fn($m, $n) => $m->multiply($n('-3'), $n('7')), '-21', '18446744073709551595'];
        yield 'product of large numbers' => [static fn($m, $n) => $m->multiply($n('4294967295'), $n('4294967295')), '-8589934591', '18446744065119617025'];
        yield 'product wraps around' => [static fn($m, $n) => $m->multiply($n('4294967296'), $n('4294967296')), '0', '0'];
        yield 'product of distant parts' => [static fn($m, $n) => $m->multiply($n('123456789012'), $n('987654321')), '-7194577391479740460', '11252166682229811156'];
        yield 'product of the least value' => [static fn($m, $n) => $m->multiply($n($signedMin), $n('-1')), $signedMin, '9223372036854775808'];
        yield 'negation' => [static fn($m, $n) => $m->negate($n('5')), '-5', '18446744073709551611'];
        yield 'negation of the least value' => [static fn($m, $n) => $m->negate($n($signedMin)), $signedMin, '9223372036854775808'];
        yield 'bitwise not' => [static fn($m, $n) => $m->bitwiseNot($n('0')), '-1', $max];
        yield 'bitwise and' => [static fn($m, $n) => $m->bitwiseAnd($n('-1'), $n('255')), '255', '255'];
        yield 'bitwise or' => [static fn($m, $n) => $m->bitwiseOr($n($signedMin), $n('1')), '-9223372036854775807', '9223372036854775809'];
        yield 'bitwise xor' => [static fn($m, $n) => $m->bitwiseXor($n('-1'), $n('1')), '-2', '18446744073709551614'];
        yield 'shift left' => [static fn($m, $n) => $m->shiftLeft($n('1'), 63), $signedMin, '9223372036854775808'];
        yield 'shift left drops the high bits' => [static fn($m, $n) => $m->shiftLeft($n('-1'), 60), '-1152921504606846976', '17293822569102704640'];
        yield 'shift left of the highest bit' => [static fn($m, $n) => $m->shiftLeft($n($signedMin), 1), '0', '0'];
        yield 'shift by zero' => [static fn($m, $n) => $m->shiftLeft($n('7'), 0), '7', '7'];
    }

    /**
     * @return iterable<non-empty-string, array{MathDriverInterface<mixed>, \Closure, string, string}>
     */
    public static function operationDataProvider(): iterable
    {
        foreach (self::driverDataProvider() as $driver => [$math]) {
            foreach (self::operations() as $name => [$operation, $signed, $unsigned]) {
                yield $driver . ', ' . $name => [$math, $operation, $signed, $unsigned];
            }
        }
    }

    /**
     * @param MathDriverInterface<mixed> $math
     * @param \Closure(MathDriverInterface<mixed>, \Closure(string): mixed): mixed $operation
     */
    #[DataProvider('operationDataProvider')]
    public function testOperationWrapsAroundSixtyFourBits(
        MathDriverInterface $math,
        \Closure $operation,
        string $signed,
        string $unsigned,
    ): void {
        $number = static fn(string $digits): mixed => $digits[0] === '-'
            ? $math->negate($math->createFromDigits(\substr($digits, 1), 10))
            : $math->createFromDigits($digits, 10);

        // @phpstan-ignore-next-line the digits of the cases are never empty
        $result = $operation($math, $number);

        self::assertSame($signed, $math->toString($result, false));
        self::assertSame($unsigned, $math->toString($result, true));
    }

    /**
     * @return iterable<non-empty-string, array{MathDriverInterface<mixed>, string, string, bool, string, string}>
     */
    public static function divisionDataProvider(): iterable
    {
        $cases = [
            'signed positive' => ['7', '2', false, '3', '1'],
            'signed negative dividend' => ['-7', '2', false, '-3', '-1'],
            'signed negative divisor' => ['7', '-2', false, '-3', '1'],
            'signed both negative' => ['-7', '-2', false, '3', '-1'],
            'unsigned maximum by two' => ['-1', '2', true, self::SIGNED_MAX, '1'],
            'unsigned maximum by ten' => ['-1', '10', true, '1844674407370955161', '5'],
            'unsigned by the highest bit' => ['-1', self::SIGNED_MIN, true, '1', self::SIGNED_MAX],
            'unsigned smaller by larger' => ['-2', '-1', true, '0', '18446744073709551614'],
            'unsigned equal' => ['-1', '-1', true, '1', '0'],
            'unsigned large by small' => ['-9', '3', true, '6148914691236517202', '1'],
        ];

        foreach (self::driverDataProvider() as $driver => [$math]) {
            foreach ($cases as $name => $case) {
                yield $driver . ', ' . $name => [$math, ...$case];
            }
        }
    }

    /**
     * @param MathDriverInterface<mixed> $math
     */
    #[DataProvider('divisionDataProvider')]
    public function testDivisionTruncatesTowardZero(
        MathDriverInterface $math,
        string $left,
        string $right,
        bool $isUnsigned,
        string $quotient,
        string $remainder,
    ): void {
        $a = self::createNumber($math, $left);
        $b = self::createNumber($math, $right);

        self::assertSame($quotient, $math->toString($math->divide($a, $b, $isUnsigned), $isUnsigned));
        self::assertSame($remainder, $math->toString($math->remainder($a, $b, $isUnsigned), $isUnsigned));
    }

    /**
     * @return iterable<non-empty-string, array{MathDriverInterface<mixed>, string, int, bool, string}>
     */
    public static function shiftRightDataProvider(): iterable
    {
        $cases = [
            'signed positive' => ['16', 2, false, '4'],
            'signed negative fills ones' => ['-8', 1, false, '-4'],
            'signed negative rounds down' => ['-7', 1, false, '-4'],
            'signed all bits' => ['-1', 63, false, '-1'],
            'unsigned fills zeros' => ['-1', 63, true, '1'],
            'unsigned by one' => ['-1', 1, true, self::SIGNED_MAX],
            'by zero' => ['-5', 0, false, '-5'],
            'unsigned by zero' => ['-5', 0, true, '18446744073709551611'],
        ];

        foreach (self::driverDataProvider() as $driver => [$math]) {
            foreach ($cases as $name => $case) {
                yield $driver . ', ' . $name => [$math, ...$case];
            }
        }
    }

    /**
     * @param MathDriverInterface<mixed> $math
     * @param int<0, 63> $bits
     */
    #[DataProvider('shiftRightDataProvider')]
    public function testShiftRightKeepsSignOfSignedNumber(
        MathDriverInterface $math,
        string $number,
        int $bits,
        bool $isUnsigned,
        string $expected,
    ): void {
        $result = $math->shiftRight(self::createNumber($math, $number), $bits, $isUnsigned);

        self::assertSame($expected, $math->toString($result, $isUnsigned));
    }

    /**
     * @param MathDriverInterface<mixed> $math
     */
    #[DataProvider('driverDataProvider')]
    public function testComparisonDependsOnSignedness(MathDriverInterface $math): void
    {
        $minusOne = self::createNumber($math, '-1');
        $one = self::createNumber($math, '1');

        self::assertLessThan(0, $math->compare($minusOne, $one, false));
        self::assertGreaterThan(0, $math->compare($minusOne, $one, true));
        self::assertSame(0, $math->compare($one, self::createNumber($math, '1'), true));
        self::assertGreaterThan(0, $math->compare(self::createNumber($math, self::SIGNED_MIN), $one, true));
        self::assertLessThan(0, $math->compare(self::createNumber($math, self::SIGNED_MIN), $one, false));
    }

    /**
     * @param MathDriverInterface<mixed> $math
     */
    #[DataProvider('driverDataProvider')]
    public function testSignAndZeroAreTold(MathDriverInterface $math): void
    {
        self::assertTrue($math->isZero($math->createFromInt(0)));
        self::assertFalse($math->isZero($math->createFromInt(1)));
        self::assertTrue($math->isNegative($math->createFromInt(-1)));
        self::assertTrue($math->isNegative(self::createNumber($math, self::SIGNED_MIN)));
        self::assertFalse($math->isNegative(self::createNumber($math, self::SIGNED_MAX)));
    }

    /**
     * @param MathDriverInterface<mixed> $math
     */
    private static function createNumber(MathDriverInterface $math, string $digits): mixed
    {
        if ($digits[0] === '-') {
            // @phpstan-ignore-next-line the digits of the cases are never empty
            return $math->negate($math->createFromDigits(\substr($digits, 1), 10));
        }

        // @phpstan-ignore-next-line the digits of the cases are never empty
        return $math->createFromDigits($digits, 10);
    }
}
