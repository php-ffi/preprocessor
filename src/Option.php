<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

/**
 * @psalm-type OptionEnum = Option::*
 */
final class Option
{
    /**
     * @var OptionEnum
     */
    public const NOTHING = 0b0000_0000;

    /**
     * @var OptionEnum
     */
    public const KEEP_EXTRA_LINE_FEEDS = 0b0000_0001;

    /**
     * @var OptionEnum
     */
    public const SKIP_BUILTIN_DIRECTIVES = 0b0000_0010;

    /**
     * @var OptionEnum
     */
    public const KEEP_DEBUG_COMMENTS = 0b0000_0100;

    /**
     * @param int-mask-of<OptionEnum> $mask
     * @param OptionEnum $expected
     * @return bool
     */
    public static function contains(int $mask, int $expected): bool
    {
        return ($mask & $expected) === $expected;
    }
}
