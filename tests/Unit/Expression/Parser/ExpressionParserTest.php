<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Expression\Parser;

use FFI\Preprocessor\Expression\Node\BinaryExpression;
use FFI\Preprocessor\Expression\Node\ConditionalExpression;
use FFI\Preprocessor\Expression\Node\ExpressionInterface;
use FFI\Preprocessor\Expression\Node\Literal;
use FFI\Preprocessor\Expression\Node\Literal\CharLiteral;
use FFI\Preprocessor\Expression\Node\Literal\IntLiteral;
use FFI\Preprocessor\Expression\Node\UnaryExpression;
use FFI\Preprocessor\Expression\Parser\Exception\SyntaxErrorException;
use FFI\Preprocessor\Expression\Parser\ExpressionParser;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ExpressionParser::class)]
#[CoversClass(SyntaxErrorException::class)]
final class ExpressionParserTest extends TestCase
{
    private static function parse(string $expression): ExpressionInterface
    {
        return (new ExpressionParser())->parse(new StringSource($expression));
    }

    /**
     * Describes the tree in the prefix notation, the operator by the short
     * name of the class of its node.
     */
    private static function describe(ExpressionInterface $node): string
    {
        $name = \substr(\strrchr($node::class, '\\') ?: '', 1);

        return match (true) {
            $node instanceof IntLiteral => $node->digits . ($node->suffix !== null ? ':' . $node->suffix->value : '')
                . '/' . $node::getBase(),
            $node instanceof CharLiteral => $node->raw . '=' . $node->value,
            $node instanceof UnaryExpression => '(' . \str_replace('Expression', '', $name) . ' '
                . self::describe($node->operand) . ')',
            $node instanceof BinaryExpression => '(' . \str_replace('Expression', '', $name) . ' '
                . self::describe($node->left) . ' ' . self::describe($node->right) . ')',
            $node instanceof ConditionalExpression => '(? ' . self::describe($node->condition) . ' '
                . self::describe($node->then) . ' ' . self::describe($node->else) . ')',
            default => self::fail('Unknown node ' . $node::class),
        };
    }

    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function treeDataProvider(): iterable
    {
        yield 'decimal' => ['42', '42/10'];
        yield 'hexadecimal' => ['0x1F', '1f/16'];
        yield 'octal' => ['017', '17/8'];
        yield 'zero' => ['0', '0/8'];
        yield 'binary' => ['0b1010', '1010/2'];
        yield 'separators' => ["1'000'000", '1000000/10'];
        yield 'leading zeros' => ['0x0001', '1/16'];
        yield 'suffix' => ['1u', '1:u/10'];
        yield 'character' => ["'a'", "'a'=a"];
        yield 'character with escapes' => ["L'\\x41\\n'", "L'\\x41\\n'=A\n"];
        yield 'parentheses' => ['((1))', '1/10'];
        yield 'unary operators' => ['-+~!1', '(Minus (Plus (BitwiseNot (LogicalNot 1/10))))'];
        yield 'multiplication before addition' => ['1 + 2 * 3', '(Add 1/10 (Mul 2/10 3/10))'];
        yield 'left associativity' => ['1 - 2 - 3', '(Sub (Sub 1/10 2/10) 3/10)'];
        yield 'parentheses change precedence' => ['(1 + 2) * 3', '(Mul (Add 1/10 2/10) 3/10)'];
        yield 'all levels' => [
            '1 || 2 && 3 | 4 ^ 5 & 6 == 7 < 8 << 9 + 10 * 11',
            '(LogicalOr 1/10 (LogicalAnd 2/10 (BitwiseOr 3/10 (BitwiseXor 4/10 (BitwiseAnd 5/10 '
                . '(Equal 6/10 (Less 7/10 (ShiftLeft 8/10 (Add 9/10 (Mul 10/10 11/10))))))))))',
        ];
        yield 'relational operators' => ['1 <= 2 >= 3 > 4', '(Greater (GreaterOrEqual (LessOrEqual 1/10 2/10) 3/10) 4/10)'];
        yield 'equality operators' => ['1 != 2 == 3', '(Equal (NotEqual 1/10 2/10) 3/10)'];
        yield 'remainder and division' => ['1 % 2 / 3', '(Div (Rem 1/10 2/10) 3/10)'];
        yield 'shift right' => ['1 >> 2', '(ShiftRight 1/10 2/10)'];
        yield 'conditional' => ['1 ? 2 : 3', '(? 1/10 2/10 3/10)'];
        yield 'conditional is right-associative' => ['1 ? 2 : 3 ? 4 : 5', '(? 1/10 2/10 (? 3/10 4/10 5/10))'];
        yield 'comma inside conditional' => ['1 ? 2, 3 : 4', '(? 1/10 (Comma 2/10 3/10) 4/10)'];
        yield 'comma' => ['1, 2, 3', '(Comma (Comma 1/10 2/10) 3/10)'];
        yield 'conditional binds looser than logical' => ['1 || 2 ? 3 : 4', '(? (LogicalOr 1/10 2/10) 3/10 4/10)'];
        yield 'unary minus of a product' => ['-1 * 2', '(Mul (Minus 1/10) 2/10)'];
        yield 'without white space' => ['1+-2', '(Add 1/10 (Minus 2/10))'];
        yield 'constants beyond uintmax' => [
            '1111111111111111111111111111 + 0x1FFFFFFFFFFFFFFFF',
            '(Add 1111111111111111111111111111/10 1ffffffffffffffff/16)',
        ];
    }

    #[DataProvider('treeDataProvider')]
    public function testExpressionIsParsedIntoTree(string $expression, string $expected): void
    {
        self::assertSame($expected, self::describe(self::parse($expression)));
    }

    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function suffixDataProvider(): iterable
    {
        yield 'u' => ['1u', 'u'];
        yield 'U' => ['1U', 'u'];
        yield 'l' => ['1l', 'l'];
        yield 'L' => ['1L', 'l'];
        yield 'ul' => ['1ul', 'ul'];
        yield 'lu' => ['1lu', 'ul'];
        yield 'll' => ['1ll', 'll'];
        yield 'LL' => ['1LL', 'll'];
        yield 'ull' => ['1ull', 'ull'];
        yield 'LLU' => ['1LLU', 'ull'];
        yield 'Ul' => ['1Ul', 'ul'];
        yield 'uLL' => ['1uLL', 'ull'];
        yield 'WBU' => ['1WBU', 'uwb'];
        yield 'wb' => ['1wb', 'wb'];
        yield 'WB' => ['1WB', 'wb'];
        yield 'uwb' => ['1uwb', 'uwb'];
        yield 'wbu' => ['1wbu', 'uwb'];
        yield 'hexadecimal with suffix' => ['0xFFu', 'u'];
        yield 'hexadecimal digit b before suffix' => ['0xBbu', 'u'];
        yield 'hexadecimal digit b before bit-precise' => ['0x1bwb', 'wb'];
        yield 'binary with suffix' => ['0b1ULL', 'ull'];
        yield 'octal with suffix' => ['017l', 'l'];
        yield 'MSVC i8' => ['1i8', 'i8'];
        yield 'MSVC I16' => ['1I16', 'i16'];
        yield 'MSVC i32' => ['1i32', 'i32'];
        yield 'MSVC i64' => ['1i64', 'i64'];
        yield 'MSVC Ui8' => ['1Ui8', 'ui8'];
        yield 'MSVC uI16' => ['1uI16', 'ui16'];
        yield 'MSVC ui32' => ['1ui32', 'ui32'];
        yield 'MSVC UI64' => ['0xFFUI64', 'ui64'];
        yield 'imaginary keeps the type' => ['1ui', 'u'];
        yield 'imaginary before the type' => ['1jLL', 'll'];
    }

    #[DataProvider('suffixDataProvider')]
    public function testSuffixIsRecognized(string $expression, string $suffix): void
    {
        $node = self::parse($expression);

        self::assertInstanceOf(IntLiteral::class, $node);
        self::assertSame($suffix, $node->suffix?->value);
    }

    /**
     * @return iterable<non-empty-string, array{string, string|null, bool, string}>
     */
    public static function imaginaryDataProvider(): iterable
    {
        yield 'i' => ['3i', null, true, '3'];
        yield 'J' => ['3J', null, true, '3'];
        yield 'after the type' => ['3ulli', 'ull', true, '3'];
        yield 'before the type' => ['3iu', 'u', true, '3'];
        yield 'hexadecimal' => ['0xBi', null, true, 'b'];
        yield 'MSVC is not imaginary' => ['3i64', 'i64', false, '3'];
        yield 'no suffix' => ['3', null, false, '3'];
    }

    #[DataProvider('imaginaryDataProvider')]
    public function testImaginarySuffixIsToldApartFromTheType(string $expression, ?string $type, bool $isImaginary, string $digits): void
    {
        $node = self::parse($expression);

        self::assertInstanceOf(IntLiteral::class, $node);
        self::assertSame($type, $node->suffix?->value);
        self::assertSame($isImaginary, $node->isImaginary);
        self::assertSame($digits, $node->digits);
    }

    public function testNodesKnowTheirPlace(): void
    {
        $node = self::parse('1 +  (2 * 3)');

        self::assertInstanceOf(BinaryExpression::class, $node);
        self::assertSame(0, $node->offset);
        self::assertInstanceOf(BinaryExpression::class, $node->right);
        self::assertSame(6, $node->right->offset);
        self::assertInstanceOf(Literal::class, $node->right->right);
        self::assertSame(10, $node->right->right->offset);
        self::assertSame('3', $node->right->right->raw);
    }

    /**
     * @return iterable<non-empty-string, array{string, string, string}>
     */
    public static function invalidExpressionDataProvider(): iterable
    {
        yield 'empty' => ['', 'Syntax error, unexpected end of input', ''];
        yield 'operator at the end' => ['1 +', 'Syntax error, unexpected end of input', ''];
        yield 'two operands' => ['1 2', 'Syntax error, unexpected "2"', '2'];
        yield 'unclosed parenthesis' => ['(1', 'Syntax error, unexpected end of input', ''];
        yield 'extra parenthesis' => ['1)', 'Syntax error, unexpected ")"', ')'];
        yield 'missing colon' => ['1 ? 2', 'Syntax error, unexpected end of input', ''];
        yield 'increment' => ['1 ++ 2', 'Syntax error, unexpected "++"', '++'];
        yield 'assignment' => ['1 = 2', 'Syntax error, unexpected "="', '='];
        yield 'compound assignment' => ['1 += 2', 'Syntax error, unexpected "+="', '+='];
        yield 'shift assignment' => ['1 <<= 2', 'Syntax error, unexpected "<<="', '<<='];
        yield 'string literal' => ['"a"', 'Syntax error, unexpected "\\"a\\""', '"a"'];
        yield 'brackets' => ['1 [ 2 ]', 'Syntax error, unexpected "["', '['];
        yield 'digraph' => ['1 <: 2', 'Syntax error, unexpected "<:"', '<:'];
        yield 'scope' => ['1 :: 2', 'Syntax error, unexpected "::"', '::'];
        yield 'identifier' => ['x', 'Syntax error, unexpected "x"', 'x'];
        $numbers = [
            'floating' => '1.5',
            'floating without integer part' => '.5',
            'floating with exponent' => '1e3',
            'floating with signed exponent' => '1e+3',
            'hexadecimal floating' => '0x1p3',
            'octal floating' => '0.5',
            'octal-looking floating' => '09.5',
            'invalid octal digit' => '08',
            'invalid octal digit after valid ones' => '0778',
            'invalid octal digit before suffix' => '08u',
            'invalid suffix' => '1x',
            'mixed case long long' => '1lL',
            'two unsigned' => '1uu',
            'unsigned on both sides' => '1ulu',
            'unsigned inside long long' => '1lul',
            'mixed case bit-precise' => '1Wb',
            'three longs' => '1lll',
            'two imaginary' => '1ij',
            'imaginary with MSVC suffix' => '1i64i',
            'MSVC suffix of no size' => '1i7',
            'hexadecimal without digits' => '0x',
            'binary without digits' => '0b2',
            'binary with invalid digit' => '0b12',
            'binary with invalid digit after separator' => "0b1'2",
            'separator before suffix' => "1'a",
        ];

        foreach ($numbers as $name => $number) {
            yield 'number of no constant, ' . $name => [$number, \sprintf('Syntax error, unexpected "%s"', $number), $number];
        }

        yield 'quote after a number starts a character' => ["1'", 'Syntax error, unexpected "\'"', "'"];

        $characters = [
            'empty' => "''",
            'not closed' => "'a",
            'not closed with a prefix' => "u8'a",
            'unknown escape' => "'\\q'",
            'hexadecimal escape without digits' => "'\\x'",
            'incomplete universal character name' => "'\\u12'",
        ];

        foreach ($characters as $name => $character) {
            yield 'invalid character, ' . $name => [$character, \sprintf('Syntax error, unexpected "%s"', $character), $character];
        }
    }

    /**
     * @param string $fragment the text the failure points to, the end of
     *        the expression in case of it is empty
     */
    #[DataProvider('invalidExpressionDataProvider')]
    public function testInvalidExpressionIsReportedWithItsPlace(string $expression, string $message, string $fragment): void
    {
        try {
            self::parse($expression);
        } catch (SyntaxErrorException $e) {
            self::assertSame($message, $e->getMessage());
            self::assertSame($fragment === '' ? \strlen($expression) : \strpos($expression, $fragment), $e->offset);
            self::assertSame(\strlen($fragment), $e->length);

            return;
        }

        self::fail('The expression is accepted');
    }

    public function testFailureIsPrintedWithSnippetOfItsSource(): void
    {
        $source = new StringSource('1 + (2 *');

        try {
            (new ExpressionParser())->parse($source);
        } catch (SyntaxErrorException $e) {
            self::assertSame($source, $e->source);
            self::assertStringContainsString('Syntax error, unexpected end of input', (string) $e);
            self::assertStringContainsString('1 + (2 *', (string) $e);

            return;
        }

        self::fail('The expression is accepted');
    }
}
