<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Expression\Evaluator\ExpressionEvaluator;

use FFI\Preprocessor\Expression\Evaluator\Exception\EvaluationException;
use FFI\Preprocessor\Expression\Evaluator\ExpressionEvaluator\CharConverter;
use FFI\Preprocessor\Expression\Node\Literal\CharLiteral;
use FFI\Preprocessor\Expression\Parser\ExpressionParser\CharParser;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CharConverter::class)]
final class CharConverterTest extends TestCase
{
    /**
     * @param non-empty-string $raw
     */
    private static function createLiteral(string $raw, int $offset = 0): CharLiteral
    {
        \assert($offset >= 0);

        $result = CharParser::parse($raw);

        return new CharLiteral($result->prefix, $result->value, $raw, $offset);
    }

    /**
     * The values are the ones of GCC 13 on Linux x86-64.
     *
     * @return iterable<non-empty-string, array{non-empty-string, int}>
     */
    public static function valueDataProvider(): iterable
    {
        yield 'letter' => ["'a'", 97];
        yield 'quote' => ["'\\''", 39];
        yield 'new line' => ["'\\n'", 10];
        yield 'null' => ["'\\0'", 0];
        yield 'octal' => ["'\\101'", 65];
        yield 'octal char is signed' => ["'\\377'", -1];
        yield 'hexadecimal char is signed' => ["'\\xff'", -1];
        yield 'octal escape ends after three digits' => ["'\\0223'", 0x1233];
        yield 'two characters' => ["'ab'", 0x6162];
        yield 'two escapes' => ["'\\xff\\xff'", 0xFFFF];
        yield 'four characters' => ["'\\xff\\xff\\xff\\xff'", -1];
        yield 'last four of five characters' => ["'abcde'", 0x62636465];
        yield 'UTF-8 character of two bytes' => ["'\xC3\xA9'", 0xC3A9];
        yield 'universal character name' => ["'\\u00E9'", 0xC3A9];
        yield 'UTF-8 prefix' => ["u8'a'", 97];
        yield 'UTF-8 prefix is unsigned' => ["u8'\\xFF'", 255];
        yield 'UTF-16 prefix' => ["u'\xC3\xA9'", 0xE9];
        yield 'UTF-16 escape' => ["u'\\xFFFF'", 0xFFFF];
        yield 'UTF-32 prefix' => ["U'\xF0\x9F\x98\x80'", 0x1F600];
        yield 'UTF-32 escape' => ["U'\\x10FFFF'", 0x10_FFFF];
        yield 'wide' => ["L'a'", 97];
        yield 'wide of two characters is the last one' => ["L'ab'", 98];
        yield 'wide universal character name' => ["L'\\U0001F600'", 0x1F600];
        yield 'byte that is not UTF-8' => ["U'\xFF'", 0xFF];
        yield 'value that does not fit is the replacement character' => ["U'\\xFFFFFFFF'", 0xFFFD];
    }

    /**
     * @param non-empty-string $raw
     */
    #[DataProvider('valueDataProvider')]
    public function testValueIsTheOneOfTheType(string $raw, int $value): void
    {
        self::assertSame($value, CharConverter::toInt(self::createLiteral($raw), new StringSource($raw)));
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, string}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'UTF-8 of two characters' => ["u8'ab'", 'Character constant too long for its type'];
        yield 'UTF-8 of a character beyond ASCII' => ["u8'\xC3\xA9'", 'Character constant too long for its type'];
        yield 'UTF-16 of two characters' => ["u'ab'", 'Character constant too long for its type'];
        yield 'UTF-32 of two characters' => ["U'ab'", 'Character constant too long for its type'];
        yield 'UTF-16 beyond the plane' => ["u'\\U0001F600'", 'Character not encodable in a single code unit'];
    }

    /**
     * @param non-empty-string $raw
     */
    #[DataProvider('invalidDataProvider')]
    public function testCharacterThatDoesNotFitItsTypeIsReportedAtTheConstant(string $raw, string $message): void
    {
        $source = new StringSource('1 + ' . $raw);

        try {
            CharConverter::toInt(self::createLiteral($raw, 3), $source);
        } catch (EvaluationException $e) {
            self::assertSame($message, $e->getMessage());
            self::assertSame($source, $e->source);
            self::assertSame(3, $e->offset);
            self::assertSame(\strlen($raw), $e->length);

            return;
        }

        self::fail('The constant is converted');
    }
}
