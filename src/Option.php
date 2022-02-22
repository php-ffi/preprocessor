<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

final class Option
{
    public const NOTHING = 0b0000_0000;
    public const KEEP_EXTRA_LINE_FEEDS = 0b0000_0001;
    public const SKIP_BUILTIN_DIRECTIVES = 0b0000_0010;
    public const KEEP_DEBUG_COMMENTS = 0b0000_0100;

    /**
     * @psalm-type OptionEnumCase = Option::*
     *
     * @param positive-int|0 $mask
     * @param OptionEnumCase $expected
     * @return bool
     */
    public static function contains(int $mask, int $expected): bool
    {
        return ($mask & $expected) === $expected;
    }
}
