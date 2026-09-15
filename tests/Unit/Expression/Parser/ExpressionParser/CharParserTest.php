<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Expression\Parser\ExpressionParser;

use FFI\Preprocessor\Expression\Node\Literal\CharLiteral\CharPrefix;
use FFI\Preprocessor\Expression\Parser\ExpressionParser\CharParser;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CharParser::class)]
final class CharParserTest extends TestCase
{
    /**
     * The replacement character U+FFFD in UTF-8
     */
    private const REPLACEMENT = "\xEF\xBF\xBD";

    /**
     * @return iterable<non-empty-string, array{non-empty-string, CharPrefix, string}>
     */
    public static function valueDataProvider(): iterable
    {
        yield 'letter' => ["'a'", CharPrefix::Plain, 'a'];
        yield 'several characters' => ["'ab'", CharPrefix::Plain, 'ab'];
        yield 'quote' => ["'\\''", CharPrefix::Plain, "'"];
        yield 'simple escapes' => ["'\\n\\t\\a\\v\\?\\\\'", CharPrefix::Plain, "\n\t\x07\x0B?\\"];
        yield 'escape character of GCC' => ["'\\e\\E'", CharPrefix::Plain, "\e\e"];
        yield 'octal' => ["'\\101'", CharPrefix::Plain, 'A'];
        yield 'octal of three digits at most' => ["'\\0223'", CharPrefix::Plain, "\x123"];
        yield 'octal byte' => ["'\\377'", CharPrefix::Plain, "\xFF"];
        yield 'hexadecimal byte' => ["'\\xff'", CharPrefix::Plain, "\xFF"];
        yield 'hexadecimal with leading zeros' => ["'\\x0041'", CharPrefix::Plain, 'A'];
        yield 'universal character name is UTF-8' => ["'\\u00E9'", CharPrefix::Plain, "\xC3\xA9"];
        yield 'UTF-8 as written' => ["'\xC3\xA9'", CharPrefix::Plain, "\xC3\xA9"];
        yield 'UTF-8 prefix keeps a byte' => ["u8'\\xFF'", CharPrefix::Utf8, "\xFF"];
        yield 'UTF-16 escape is UTF-8' => ["u'\\xE9'", CharPrefix::Utf16, "\xC3\xA9"];
        yield 'UTF-32 escape is UTF-8' => ["U'\\x1F600'", CharPrefix::Utf32, "\xF0\x9F\x98\x80"];
        yield 'wide' => ["L'\\U0001F600'", CharPrefix::Wide, "\xF0\x9F\x98\x80"];
        yield 'byte beyond its type' => ["'\\x100'", CharPrefix::Plain, self::REPLACEMENT];
        yield 'octal beyond a byte' => ["'\\777'", CharPrefix::Plain, self::REPLACEMENT];
        yield 'UTF-16 code unit beyond its type' => ["u'\\x10000'", CharPrefix::Utf16, self::REPLACEMENT];
        yield 'code point beyond Unicode' => ["U'\\U00110000'", CharPrefix::Utf32, self::REPLACEMENT];
        yield 'escape beyond Unicode' => ["U'\\xFFFFFFFF'", CharPrefix::Utf32, self::REPLACEMENT];
        yield 'surrogate' => ["'\\uD800'", CharPrefix::Plain, self::REPLACEMENT];
        yield 'escape beyond an integer' => ["U'\\x1FFFFFFFFFFFFFFFF'", CharPrefix::Utf32, self::REPLACEMENT];
    }

    /**
     * @param non-empty-string $raw
     */
    #[DataProvider('valueDataProvider')]
    public function testEscapeSequencesAreExpanded(string $raw, CharPrefix $prefix, string $value): void
    {
        $result = CharParser::parse($raw);

        self::assertSame($prefix, $result->prefix);
        self::assertSame($value, $result->value);
    }
}
