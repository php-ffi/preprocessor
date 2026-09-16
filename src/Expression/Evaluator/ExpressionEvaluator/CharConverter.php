<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\ExpressionEvaluator;

use FFI\Preprocessor\Expression\Evaluator\Exception\EvaluationException;
use FFI\Preprocessor\Expression\Node\Literal\CharLiteral;
use FFI\Preprocessor\Expression\Node\Literal\CharLiteral\CharPrefix;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Value of a character constant in its type (6.4.4.5). The values not fixed
 * by the standard are the ones of GCC on Linux: `char` is signed, the value
 * of `'ab'` is `'a' * 256 + 'b'` truncated to `int`, `wchar_t` is a signed
 * 32-bit integer and `L'ab'` is `L'b'`.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.5 Character constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Expression\Evaluator
 */
final class CharConverter
{
    /**
     * Characters of a UTF-8 string
     */
    private const UTF8_CHARACTER_PATTERN = '//u';

    /**
     * @param ReadableInterface $source the text of the expression, the place
     *        of a failure
     * @throws EvaluationException in case of a `u8`, `u` or `U` constant
     *         has more than one character or one that does not fit its code
     *         unit
     */
    public static function toInt(CharLiteral $node, ReadableInterface $source): int
    {
        if ($node->prefix === CharPrefix::Plain) {
            return self::toPlainInt(self::splitBytes($node->value));
        }

        if ($node->prefix === CharPrefix::Utf8) {
            return self::toSingleCodeUnit(self::splitBytes($node->value), $node, $source);
        }

        $codePoints = self::splitCodePoints($node->value);

        if ($node->prefix === CharPrefix::Wide) {
            return self::toInt32($codePoints[\count($codePoints) - 1]);
        }

        return self::toSingleCodeUnit($codePoints, $node, $source);
    }

    /**
     * A single `char` converted to `int`, the bytes of several characters as
     * an `int`.
     *
     * @param non-empty-list<int<0, max>> $bytes
     */
    private static function toPlainInt(array $bytes): int
    {
        if (\count($bytes) === 1) {
            return self::toInt8($bytes[0]);
        }

        $value = 0;

        foreach ($bytes as $byte) {
            $value = (($value << 8) | $byte) & 0xFFFF_FFFF;
        }

        return self::toInt32($value);
    }

    /**
     * @param non-empty-list<int<0, max>> $units
     * @throws EvaluationException in case of there is more than one unit or it does not fit the type
     */
    private static function toSingleCodeUnit(array $units, CharLiteral $node, ReadableInterface $source): int
    {
        if (\count($units) > 1) {
            throw EvaluationException::becauseCharacterIsTooLong($source, $node->offset, \strlen($node->raw));
        }

        if ($units[0] > $node->prefix->getMaxCodeUnit()) {
            throw EvaluationException::becauseCharacterIsNotEncodable($source, $node->offset, \strlen($node->raw));
        }

        return $units[0];
    }

    /**
     * @return non-empty-list<int<0, 255>>
     */
    private static function splitBytes(string $value): array
    {
        $bytes = \unpack('C*', $value);

        \assert(\is_array($bytes) && $bytes !== [], 'a character constant has a character');

        /** @var non-empty-list<int<0, 255>> */
        return \array_values($bytes);
    }

    /**
     * Returns the code points of a UTF-8 string, the bytes of one that is not
     * UTF-8.
     *
     * @return non-empty-list<int<0, max>>
     */
    private static function splitCodePoints(string $value): array
    {
        $characters = \preg_split(self::UTF8_CHARACTER_PATTERN, $value, -1, \PREG_SPLIT_NO_EMPTY);

        if ($characters === false || $characters === []) {
            return self::splitBytes($value);
        }

        return \array_map(self::decodeUtf8(...), $characters);
    }

    /**
     * Returns the code point of a character encoded in UTF-8.
     *
     * @return int<0, max>
     */
    private static function decodeUtf8(string $character): int
    {
        $size = \strlen($character);

        if ($size === 1) {
            return \ord($character);
        }

        if (\function_exists('mb_ord')) {
            /** @var int<0, max> */
            return \mb_ord($character, 'UTF-8');
        }

        // Decoding without the mbstring extension: the payload bits of the first byte, then of the continuation bytes
        $codePoint = \ord($character[0]) & (0xFF >> ($size + 1));

        for ($i = 1; $i < $size; ++$i) {
            $codePoint = ($codePoint << 6) | (\ord($character[$i]) & 0x3F);
        }

        return $codePoint;
    }

    /**
     * Reads the 8 bits as a signed `char`.
     */
    private static function toInt8(int $value): int
    {
        if ($value > 0x7F) {
            return $value - 0x100;
        }

        return $value;
    }

    /**
     * Reads the 32 bits as a signed integer.
     */
    private static function toInt32(int $value): int
    {
        if ($value > 0x7FFF_FFFF) {
            return $value - 0x1_0000_0000;
        }

        return $value;
    }
}
