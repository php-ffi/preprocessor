<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\Literal\IntLiteral;

/**
 * Suffix of an integer constant (6.4.4.2), in its lowercase spelling. In a
 * conditional directive every integer constant is `intmax_t` or `uintmax_t`
 * (6.10.2p13), so only the unsignedness matters there.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.2 Integer constants
 * @link https://learn.microsoft.com/en-us/cpp/c-language/c-integer-constants MSVC, integer constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
enum IntTypeSuffix: string
{
    /**
     * `u`, `unsigned int`
     */
    case Unsigned = 'u';

    /**
     * `l`, `long int`
     */
    case Long = 'l';

    /**
     * `ul` or `lu`, `unsigned long int`
     */
    case UnsignedLong = 'ul';

    /**
     * `ll`, `long long int`
     */
    case LongLong = 'll';

    /**
     * `ull` or `llu`, `unsigned long long int`
     */
    case UnsignedLongLong = 'ull';

    /**
     * `wb`, a bit-precise integer type, `_BitInt(N)`
     */
    case BitPrecise = 'wb';

    /**
     * `uwb`, an unsigned bit-precise integer type, `unsigned _BitInt(N)`
     */
    case UnsignedBitPrecise = 'uwb';

    /**
     * MSVC: `i8` or `I8`, `__int8`
     */
    case Int8 = 'i8';

    /**
     * MSVC: `i16` or `I16`, `__int16`
     */
    case Int16 = 'i16';

    /**
     * MSVC: `i32` or `I32`, `__int32`
     */
    case Int32 = 'i32';

    /**
     * MSVC: `i64` or `I64`, `__int64`
     */
    case Int64 = 'i64';

    /**
     * MSVC: `ui8` in any case, `unsigned __int8`
     */
    case UnsignedInt8 = 'ui8';

    /**
     * MSVC: `ui16` in any case, `unsigned __int16`
     */
    case UnsignedInt16 = 'ui16';

    /**
     * MSVC: `ui32` in any case, `unsigned __int32`
     */
    case UnsignedInt32 = 'ui32';

    /**
     * MSVC: `ui64` in any case, `unsigned __int64`
     */
    case UnsignedInt64 = 'ui64';

    /**
     * Whether the suffix gives an unsigned type.
     */
    public function isUnsigned(): bool
    {
        return match ($this) {
            self::Unsigned,
            self::UnsignedLong,
            self::UnsignedLongLong,
            self::UnsignedBitPrecise,
            self::UnsignedInt8,
            self::UnsignedInt16,
            self::UnsignedInt32,
            self::UnsignedInt64 => true,
            default => false,
        };
    }
}
