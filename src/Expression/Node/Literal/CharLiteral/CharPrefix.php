<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\Literal\CharLiteral;

/**
 * Encoding prefix of a character constant (6.4.4.5), which tells its type.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.5 Character constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
enum CharPrefix: string
{
    /**
     * No prefix, `'a'`: an integer character constant of type `int`, a
     * single byte of it is a `char`, which is signed
     */
    case Plain = '';

    /**
     * `u8'a'`: a UTF-8 character constant of type `char8_t`
     */
    case Utf8 = 'u8';

    /**
     * `u'a'`: a UTF-16 character constant of type `char16_t`
     */
    case Utf16 = 'u';

    /**
     * `U'a'`: a UTF-32 character constant of type `char32_t`
     */
    case Utf32 = 'U';

    /**
     * `L'a'`: a `wchar_t` character constant, a signed 32-bit integer as in
     * GCC on Linux
     */
    case Wide = 'L';

    /**
     * Whether the type of the constant is unsigned.
     */
    public function isUnsigned(): bool
    {
        return match ($this) {
            self::Utf8, self::Utf16, self::Utf32 => true,
            self::Plain, self::Wide => false,
        };
    }

    /**
     * Returns the greatest value of a single code unit of the type, the
     * greatest value of an octal or a hexadecimal escape sequence.
     *
     * @return int<0, max>
     */
    public function getMaxCodeUnit(): int
    {
        return match ($this) {
            self::Plain, self::Utf8 => 0xFF,
            self::Utf16 => 0xFFFF,
            self::Utf32, self::Wide => 0xFFFF_FFFF,
        };
    }
}
