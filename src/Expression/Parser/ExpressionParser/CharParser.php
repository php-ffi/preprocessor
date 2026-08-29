<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Parser\ExpressionParser;

use FFI\Preprocessor\Expression\Node\Literal\CharLiteral\CharPrefix;
use FFI\Preprocessor\Expression\Parser\ExpressionParser\CharParser\CharParserResult;

/**
 * Parses the text of a character constant the lexer has read: the encoding
 * prefix and the characters between the quotes with the escape sequences
 * expanded.
 *
 * - The characters written as is are copied byte by byte.
 * - A universal character name (`é`) becomes its UTF-8 encoding.
 * - An octal or a hexadecimal escape (`\101`, `\x41`) becomes a single byte
 *   in a constant without a prefix or with `u8`, the UTF-8 encoding of the
 *   code point in a wide one (`L`, `u`, `U`).
 * - A value that does not fit (a code unit above the limit of the type, a
 *   code point above `U+10FFFF` or a surrogate) becomes U+FFFD.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.5 Character constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Expression\Parser
 */
final class CharParser
{
    /**
     * An escape sequence: a simple one (with `\e` and `\E` of GCC), an octal
     * one, a hexadecimal one and a universal character name of either size
     */
    private const ESCAPE_PATTERN = '/\\\\(?:(?<simple>[\'"?\\\\abefnrtvE])|(?<octal>[0-7]{1,3})'
        . '|x(?<hex>[0-9A-Fa-f]++)|u(?<short>[0-9A-Fa-f]{4})|U(?<long>[0-9A-Fa-f]{8}))/';

    /**
     * The characters of the simple escape sequences by the character after
     * the backslash
     */
    private const SIMPLE_ESCAPES = [
        "'" => "'",
        '"' => '"',
        '?' => '?',
        '\\' => '\\',
        'a' => "\x07",
        'b' => "\x08",
        'e' => "\e",
        'E' => "\e",
        'f' => "\f",
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'v' => "\v",
    ];

    /**
     * The greatest code point of Unicode
     */
    private const MAX_CODE_POINT = 0x10_FFFF;

    /**
     * The first and the last of the surrogates, which are no characters and
     * have no UTF-8 encoding
     */
    private const MIN_SURROGATE = 0xD800;

    private const MAX_SURROGATE = 0xDFFF;

    /**
     * The greatest byte, the greatest code unit of a constant without a
     * prefix and of a `u8` one
     */
    private const MAX_BYTE = 0xFF;

    /**
     * The replacement character U+FFFD in UTF-8
     */
    private const REPLACEMENT = "\xEF\xBF\xBD";

    /**
     * @param non-empty-string $raw the character constant as the lexer has
     *        read it, with its prefix and its quotes
     */
    public static function parse(string $raw): CharParserResult
    {
        $quote = \strpos($raw, "'");

        \assert($quote !== false, 'a character constant has a quote');

        $prefix = CharPrefix::from(\substr($raw, 0, $quote));

        return new CharParserResult($prefix, self::decodeBody(\substr($raw, $quote + 1, -1), $prefix));
    }

    /**
     * Expands the escape sequences of the characters between the quotes.
     */
    private static function decodeBody(string $body, CharPrefix $prefix): string
    {
        $result = \preg_replace_callback(
            pattern: self::ESCAPE_PATTERN,
            callback: static fn(array $match): string => self::decodeEscape($match, $prefix),
            subject: $body,
            flags: \PREG_UNMATCHED_AS_NULL,
        );

        \assert($result !== null);

        return $result;
    }

    /**
     * @param array<array-key, string|null> $match
     */
    private static function decodeEscape(array $match, CharPrefix $prefix): string
    {
        if ($match['simple'] !== null) {
            return self::SIMPLE_ESCAPES[$match['simple']];
        }

        if ($match['octal'] !== null) {
            return self::encodeCodeUnit((int) \octdec($match['octal']), $prefix);
        }

        if ($match['hex'] !== null) {
            return self::encodeCodeUnit(self::parseHex($match['hex']), $prefix);
        }

        return self::encodeCodePoint(self::parseHex($match['short'] ?? $match['long'] ?? ''));
    }

    /**
     * @return int|null {@see null} in case of the value does not fit a PHP
     *         integer, which is beyond any code unit
     */
    private static function parseHex(string $digits): ?int
    {
        $value = \hexdec($digits);

        if (!\is_int($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Encodes the value of an octal or a hexadecimal escape sequence, a code
     * unit of the type of the constant: a byte in a constant without a
     * prefix or with `u8`, a code point in a wide one.
     */
    private static function encodeCodeUnit(?int $value, CharPrefix $prefix): string
    {
        $max = $prefix->getMaxCodeUnit();

        if ($value === null || $value > $max) {
            return self::REPLACEMENT;
        }

        if ($max === self::MAX_BYTE) {
            return \chr($value);
        }

        return self::encodeCodePoint($value);
    }

    private static function encodeCodePoint(?int $value): string
    {
        if ($value === null || $value > self::MAX_CODE_POINT) {
            return self::REPLACEMENT;
        }

        if ($value >= self::MIN_SURROGATE && $value <= self::MAX_SURROGATE) {
            return self::REPLACEMENT;
        }

        // The extension is not a dependency, the encoding below is the
        // fallback without it
        if (\function_exists('mb_chr')) {
            return \mb_chr($value, 'UTF-8');
        }

        return match (true) {
            $value < 0x80 => \chr($value),
            $value < 0x800 => \chr(0xC0 | $value >> 6)
                . \chr(0x80 | $value & 0x3F),
            $value < 0x1_0000 => \chr(0xE0 | $value >> 12)
                . \chr(0x80 | $value >> 6 & 0x3F)
                . \chr(0x80 | $value & 0x3F),
            default => \chr(0xF0 | $value >> 18)
                . \chr(0x80 | $value >> 12 & 0x3F)
                . \chr(0x80 | $value >> 6 & 0x3F)
                . \chr(0x80 | $value & 0x3F),
        };
    }
}
