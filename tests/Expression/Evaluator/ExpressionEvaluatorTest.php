<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Expression\Evaluator;

use FFI\Preprocessor\Expression\Evaluator\Exception\EvaluationException;
use FFI\Preprocessor\Expression\Evaluator\ExpressionEvaluator;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\BcMathDriver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\GmpDriver;
use FFI\Preprocessor\Expression\Evaluator\Math\Driver\NativeX64Driver;
use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverInterface;
use FFI\Preprocessor\Expression\Evaluator\Value;
use FFI\Preprocessor\Expression\Parser\ExpressionParser;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ExpressionEvaluator::class)]
#[CoversClass(Value::class)]
#[CoversClass(EvaluationException::class)]
final class ExpressionEvaluatorTest extends TestCase
{
    /**
     * @return array<non-empty-string, MathDriverInterface<mixed>>
     */
    private static function drivers(): array
    {
        $drivers = [];

        if (\PHP_INT_SIZE >= 8) {
            $drivers['native 64-bit'] = new NativeX64Driver();
        }

        if (\extension_loaded('gmp')) {
            $drivers['GMP'] = new GmpDriver();
        }

        if (\extension_loaded('bcmath')) {
            $drivers['BCMath'] = new BcMathDriver();
        }

        return $drivers;
    }

    /**
     * @param MathDriverInterface<mixed> $math
     */
    private static function evaluate(string $expression, MathDriverInterface $math): Value
    {
        $source = new StringSource($expression);

        return (new ExpressionEvaluator($math))->evaluate((new ExpressionParser())->parse($source), $source);
    }

    /**
     * @return iterable<non-empty-string, array{string, string, bool}>
     */
    private static function values(): iterable
    {
        yield 'constant' => ['42', '42', false];
        yield 'unsigned suffix' => ['42u', '42', true];
        yield 'long suffix is signed' => ['42LL', '42', false];
        yield 'hexadecimal beyond intmax is unsigned' => ['0xFFFFFFFFFFFFFFFF', '18446744073709551615', true];
        yield 'decimal beyond intmax is unsigned' => ['18446744073709551615', '18446744073709551615', true];
        yield 'greatest signed' => ['9223372036854775807', '9223372036854775807', false];
        yield 'least signed' => ['-9223372036854775807 - 1', '-9223372036854775808', false];
        yield 'character' => ["'a'", '97', false];
        yield 'negative character' => ["'\\xff'", '-1', false];
        yield 'UTF-8 character is unsigned' => ["u8'a'", '97', true];
        yield 'wide character is signed' => ["L'a'", '97', false];
        yield 'UTF-32 character is unsigned' => ["U'\\x10FFFF'", '1114111', true];
        yield 'arithmetic' => ['1 + 2 * 3 - 4 / 2 % 3', '5', false];
        yield 'division truncates toward zero' => ['-7 / 2', '-3', false];
        yield 'remainder of a negative dividend' => ['-7 % 2', '-1', false];
        yield 'unary operators' => ['-(+5) + ~0 + !0 + !7', '-5', false];
        yield 'bitwise operators' => ['0xF0 | 0x0F ^ 0xFF & 0x3C', '243', false];
        yield 'shifts' => ['1 << 62 >> 61', '2', false];
        yield 'arithmetic shift of a negative' => ['-16 >> 2', '-4', false];
        yield 'logical shift of an unsigned' => ['-1u >> 63', '1', true];
        yield 'shift has the type of its left operand' => ['1 << 1u', '2', false];
        yield 'shift by a negative count shifts the other way' => ['(4 << -1) + (8 >> -1)', '18', false];
        yield 'shift right by the width' => ['(1 >> 64) + (-8 >> 70)', '-1', false];
        yield 'unsigned shift left by the width' => ['1u << 64', '0', true];
        yield 'signed shift of a negative' => ['-1 << 1', '-2', false];
        yield 'comparison of signed' => ['-1 < 0', '1', false];
        yield 'comparison of unsigned' => ['-1 > 0u', '1', false];
        yield 'equality converts to unsigned' => ['0xFFFFFFFFFFFFFFFF == -1', '1', false];
        yield 'negative unsigned' => ['-1u', '18446744073709551615', true];
        yield 'unsigned wraps around' => ['0u - 1', '18446744073709551615', true];
        yield 'unsigned product wraps around' => ['0xFFFFFFFFFFFFFFFF * 2', '18446744073709551614', true];
        yield 'logical and' => ['2 && 3', '1', false];
        yield 'logical or' => ['0 || 0', '0', false];
        yield 'conditional' => ['1 ? 2 : 3', '2', false];
        yield 'conditional takes the common type' => ['1 ? -1 : 0u', '18446744073709551615', true];
        yield 'conditional type of the branch not taken' => ['0 ? 1u : -1', '18446744073709551615', true];
        yield 'nested conditional' => ['0 ? 1 : 0 ? 2 : 3', '3', false];
        yield 'not evaluated division by zero' => ['0 && 1 / 0', '0', false];
        yield 'not evaluated division by zero after or' => ['1 || 1 / 0', '1', false];
        yield 'not evaluated branch' => ['0 ? 1 / 0 : 1', '1', false];
        yield 'not evaluated other branch' => ['1 ? 1 : 1 % 0', '1', false];
        yield 'not evaluated overflow' => ['0 && 9223372036854775807 + 1', '0', false];
        yield 'not evaluated shift' => ['0 && 1 << 64', '0', false];
        yield 'not evaluated comma' => ['0 && (1, 2)', '0', false];
        yield 'comma in the branch not taken' => ['1 ? 2 : (3, 4)', '2', false];
        yield 'unsigned overflow is no error' => ['0xFFFFFFFFFFFFFFFFu + 1', '0', true];
        yield 'least by one' => ['(-9223372036854775807 - 1) / 1', '-9223372036854775808', false];
        yield 'least by minus one unsigned' => ['(-9223372036854775807 - 1) / -1u', '0', true];
        yield 'shift into the sign of unsigned' => ['1u << 63', '9223372036854775808', true];
        yield 'product of negatives' => ['-3037000499 * 3037000499', '-9223372030926249001', false];
    }

    /**
     * @return iterable<non-empty-string, array{MathDriverInterface<mixed>, string, string, bool}>
     */
    public static function valueDataProvider(): iterable
    {
        foreach (self::drivers() as $driver => $math) {
            foreach (self::values() as $name => $case) {
                yield $driver . ', ' . $name => [$math, ...$case];
            }
        }
    }

    /**
     * @param MathDriverInterface<mixed> $math
     */
    #[DataProvider('valueDataProvider')]
    public function testExpressionIsEvaluated(MathDriverInterface $math, string $expression, string $decimal, bool $isUnsigned): void
    {
        $value = self::evaluate($expression, $math);

        self::assertSame($decimal, $value->decimal);
        self::assertSame($isUnsigned, $value->isUnsigned);
        self::assertSame($decimal !== '0', $value->isTrue);
    }

    /**
     * @return iterable<non-empty-string, array{string, string, string}>
     */
    private static function failures(): iterable
    {
        yield 'division by zero' => ['1 / 0', 'Division by zero in the expression', '1 /'];
        yield 'remainder by zero' => ['1 % (2 - 2)', 'Division by zero in the expression', '1 %'];
        yield 'unsigned division by zero' => ['1u / 0', 'Division by zero in the expression', '1u /'];
        yield 'signed sum overflow' => ['9223372036854775807 + 1', 'Integer overflow in the expression', '9223372036854775807 +'];
        yield 'signed difference overflow' => ['-9223372036854775807 - 2', 'Integer overflow in the expression', '-9223372036854775807 -'];
        yield 'signed product overflow' => ['4294967296 * 4294967296', 'Integer overflow in the expression', '4294967296 *'];
        yield 'product of minus one and the least' => ['-1 * (-9223372036854775807 - 1)', 'Integer overflow in the expression', '-1 *'];
        yield 'negation of the least' => ['-(-9223372036854775807 - 1)', 'Integer overflow in the expression', '-(-'];
        yield 'least divided by minus one' => ['(-9223372036854775807 - 1) / -1', 'Integer overflow in the expression', '(-'];
        yield 'least remainder by minus one' => ['(-9223372036854775807 - 1) % -1', 'Integer overflow in the expression', '(-'];
        yield 'signed shift by the width' => ['1 << 64', 'Integer overflow in the expression', '1 <<'];
        yield 'shift by large unsigned' => ['1 << 0xFFFFFFFFFFFFFFFF', 'Integer overflow in the expression', '1 <<'];
        yield 'signed shift into the sign' => ['1 << 63', 'Integer overflow in the expression', '1 <<'];
        yield 'signed shift beyond the bits' => ['3 << 62', 'Integer overflow in the expression', '3 <<'];
        yield 'evaluated comma' => ['1, 2', 'Comma operator in the evaluated part of the expression', '1,'];
        yield 'comma in the branch taken' => ['1 ? (2, 3) : 4', 'Comma operator in the evaluated part of the expression', '2,'];
        yield 'division by zero in the right of or' => ['0 || 1 / 0', 'Division by zero in the expression', '1 /'];
        yield 'too large decimal' => ['18446744073709551616', 'Integer constant is too large for its type', '18446744073709551616'];
        yield 'too large hexadecimal' => ['0x1FFFFFFFFFFFFFFFF', 'Integer constant is too large for its type', '0x1FFFFFFFFFFFFFFFF'];
        yield 'too large octal' => ['02000000000000000000000', 'Integer constant is too large for its type', '02000000000000000000000'];
        yield 'too large binary' => ['0b1' . \str_repeat('0', 64), 'Integer constant is too large for its type', '0b1' . \str_repeat('0', 64)];
        yield 'character too long' => ["1 + u8'ab'", 'Character constant too long for its type', "u8'ab'"];
        yield 'character too long in the part not evaluated' => ["0 && U'ab'", 'Character constant too long for its type', "U'ab'"];
        yield 'character not encodable' => ["u'\\U0001F600'", 'Character not encodable in a single code unit', "u'\\U0001F600'"];
        yield 'imaginary constant' => ['1 + 3i', 'Imaginary constant in the expression', '3i'];
        yield 'imaginary in the part not evaluated' => ['0 && 3uj', 'Imaginary constant in the expression', '3uj'];
        yield 'too large in the part not evaluated' => ['0 && 1 + 18446744073709551616', 'Integer constant is too large for its type', '18446744073709551616'];
        yield 'too large with leading zeros' => ['0x0001FFFFFFFFFFFFFFFF', 'Integer constant is too large for its type', '0x0001FFFFFFFFFFFFFFFF'];
    }

    /**
     * @return iterable<non-empty-string, array{MathDriverInterface<mixed>, string, string, string}>
     */
    public static function failureDataProvider(): iterable
    {
        foreach (self::drivers() as $driver => $math) {
            foreach (self::failures() as $name => $case) {
                yield $driver . ', ' . $name => [$math, ...$case];
            }
        }
    }

    /**
     * @param MathDriverInterface<mixed> $math
     * @param string $fragment the text the failure points to, the start of
     *        the operation that fails
     */
    #[DataProvider('failureDataProvider')]
    public function testInvalidValueIsReportedWithItsPlace(MathDriverInterface $math, string $expression, string $message, string $fragment): void
    {
        try {
            self::evaluate($expression, $math);
        } catch (EvaluationException $e) {
            self::assertSame($message, $e->getMessage());
            self::assertSame(\strpos($expression, $fragment), $e->offset);

            if ($e->length > 0) {
                self::assertSame(\strlen($fragment), $e->length);
            }

            return;
        }

        self::fail('The expression is evaluated');
    }
}
