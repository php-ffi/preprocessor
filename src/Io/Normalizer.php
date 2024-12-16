<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Io;

final class Normalizer
{
    /**
     * @psalm-taint-sink file $pathname
     * @param non-empty-string $pathname
     * @param non-empty-string $separator
     */
    public static function normalize(string $pathname, string $separator = \DIRECTORY_SEPARATOR): string
    {
        return \rtrim(\str_replace(['\\', '/'], $separator, $pathname), $separator);
    }
}
