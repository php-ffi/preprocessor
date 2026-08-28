<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\PathMatcher;

use FFI\Preprocessor\FileSystem\PathMatcherInterface;

final class WindowsPathMatcher implements PathMatcherInterface
{
    /**
     * A pathname that starts with a slash, a backslash, or a drive (`C:`)
     * is absolute, `\\server\share` and `C:a.h`.
     *
     * @link https://github.com/gcc-mirror/gcc/blob/master/include/filenames.h IS_DOS_ABSOLUTE_PATH of GCC
     */
    private const ABSOLUTE_PATTERN = '/\A(?:[\/\\\\]|[A-Za-z]:)/';

    public function isAbsolute(string $pathname): bool
    {
        return \preg_match(self::ABSOLUTE_PATTERN, $pathname) === 1;
    }
}
