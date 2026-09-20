<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Parser\ExpressionParser;

use FFI\Preprocessor\Expression\Node\Literal\IntLiteral\IntTypeSuffix;
use FFI\Preprocessor\Expression\Parser\ExpressionParser\IntSuffixParser\IntSuffixParserResult;

/**
 * Parses the suffix at the end of an integer constant the lexer has read:
 * the type suffix of C23 (`u`, `l`, `ll`, `wb` and their combinations), the
 * imaginary suffix of GCC (`i`, `j`) before or after it, or the suffix of a
 * sized type of MSVC (`i64`, `ui64`).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.2 Integer constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Expression\Parser
 */
final class IntSuffixParser
{
    /**
     * The letters of a suffix at the end of the constant. A letter of a
     * suffix is no digit of any base, except `b`, which is a hexadecimal
     * digit unless it follows the `w` of `wb`.
     */
    private const SUFFIX_PATTERN = '/(?:[uU]?[iI](?:8|16|32|64)|[uUlLwWbBiIjJ]*+)\z/';

    /**
     * The hexadecimal digits the letters of a suffix may start with: no
     * suffix starts with `b`
     */
    private const HEX_DIGITS = 'bB';

    /**
     * The suffix of a sized type of MSVC: `u` and the size
     */
    private const MSVC_SUFFIX_PATTERN = '/\A(?<unsigned>[uU]?)[iI](?<size>8|16|32|64)\z/';

    /**
     * The imaginary suffix of GCC before or after the type suffix
     */
    private const IMAGINARY_PATTERN = '/\A[iIjJ]|[iIjJ]\z/';

    /**
     * The `u` of an unsigned suffix before or after the size
     */
    private const UNSIGNED_PATTERN = '/\A[uU]|[uU]\z/';

    /**
     * The sizes as written by their lowercase spelling, nothing after an `u`
     * alone
     */
    private const SIZES = [
        '' => '',
        'l' => 'l',
        'L' => 'l',
        'll' => 'll',
        'LL' => 'll',
        'wb' => 'wb',
        'WB' => 'wb',
    ];

    /**
     * The lowercase spelling of an unsigned suffix before its size
     */
    private const UNSIGNED = 'u';

    /**
     * The lowercase spelling of a suffix of MSVC before its size
     */
    private const MSVC_SIZE = 'i';

    /**
     * @param non-empty-string $literal the integer constant as the lexer has
     *        read it, its suffix valid
     */
    public static function parse(string $literal): IntSuffixParserResult
    {
        \preg_match(self::SUFFIX_PATTERN, $literal, $match, \PREG_OFFSET_CAPTURE);

        $letters = $match[0][0] ?? '';
        /** @var int<0, max> $offset */
        $offset = $match[0][1] ?? \strlen($literal);
        $offset += \strspn($letters, self::HEX_DIGITS);
        $spelling = \substr($literal, $offset);

        if (\preg_match(self::MSVC_SUFFIX_PATTERN, $spelling, $msvc) === 1) {
            $suffix = \strtolower($msvc['unsigned']) . self::MSVC_SIZE . $msvc['size'];

            return new IntSuffixParserResult(IntTypeSuffix::from($suffix), false, $offset);
        }

        $type = \preg_replace(self::IMAGINARY_PATTERN, '', $spelling, 1, $imaginary);

        return new IntSuffixParserResult(self::parseType((string) $type), $imaginary === 1, $offset);
    }

    /**
     * @param string $spelling the type suffix as written, without the
     *        imaginary suffix
     */
    private static function parseType(string $spelling): ?IntTypeSuffix
    {
        if ($spelling === '') {
            return null;
        }

        $size = (string) \preg_replace(self::UNSIGNED_PATTERN, '', $spelling, 1, $unsigned);
        $type = self::SIZES[$size];

        if ($unsigned === 1) {
            $type = self::UNSIGNED . $type;
        }

        return IntTypeSuffix::from($type);
    }
}
