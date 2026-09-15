<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Expression\Parser\ExpressionParser;

use FFI\Preprocessor\Expression\Node\Literal\IntLiteral\IntTypeSuffix;
use FFI\Preprocessor\Expression\Parser\ExpressionParser\IntSuffixParser;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IntSuffixParser::class)]
final class IntSuffixParserTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{non-empty-string, IntTypeSuffix|null, bool, int}>
     */
    public static function suffixDataProvider(): iterable
    {
        yield 'none' => ['42', null, false, 2];
        yield 'u' => ['42u', IntTypeSuffix::Unsigned, false, 2];
        yield 'L' => ['42L', IntTypeSuffix::Long, false, 2];
        yield 'Lu' => ['42Lu', IntTypeSuffix::UnsignedLong, false, 2];
        yield 'LL' => ['42LL', IntTypeSuffix::LongLong, false, 2];
        yield 'Ull' => ['42Ull', IntTypeSuffix::UnsignedLongLong, false, 2];
        yield 'WB' => ['42WB', IntTypeSuffix::BitPrecise, false, 2];
        yield 'wbU' => ['42wbU', IntTypeSuffix::UnsignedBitPrecise, false, 2];
        yield 'i64' => ['42i64', IntTypeSuffix::Int64, false, 2];
        yield 'UI8' => ['42UI8', IntTypeSuffix::UnsignedInt8, false, 2];
        yield 'imaginary' => ['42j', null, true, 2];
        yield 'imaginary after the type' => ['42llI', IntTypeSuffix::LongLong, true, 2];
        yield 'imaginary before the type' => ['42iU', IntTypeSuffix::Unsigned, true, 2];
        yield 'hexadecimal digit b' => ['0x2B', null, false, 4];
        yield 'hexadecimal digits b before a suffix' => ['0xbBu', IntTypeSuffix::Unsigned, false, 4];
        yield 'hexadecimal digit b before wb' => ['0xbwb', IntTypeSuffix::BitPrecise, false, 3];
        yield 'binary' => ['0b101ul', IntTypeSuffix::UnsignedLong, false, 5];
    }

    /**
     * @param non-empty-string $literal
     */
    #[DataProvider('suffixDataProvider')]
    public function testSuffixIsFoundAtTheEndOfTheConstant(string $literal, ?IntTypeSuffix $type, bool $isImaginary, int $offset): void
    {
        $result = IntSuffixParser::parse($literal);

        self::assertSame($type, $result->type);
        self::assertSame($isImaginary, $result->isImaginary);
        self::assertSame($offset, $result->offset);
    }
}
