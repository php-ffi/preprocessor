<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Io;

final class Normalizer
{
    /**
     * @var string
     */
    private const DIRECTORY_SEPARATOR = \DIRECTORY_SEPARATOR;

    /**
     * @param string $pathname
     * @param string $separator
     * @return string
     */
    public static function normalize(string $pathname, string $separator = self::DIRECTORY_SEPARATOR): string
    {
        return \rtrim(\str_replace(['\\', '/'], $separator, $pathname), $separator);
    }
}
