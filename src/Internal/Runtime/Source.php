<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Runtime;

/**
 * @internal
 */
final class Source
{
    public static function insert(string $source, string $insert, int $offset): string
    {
        return self::replace($source, $insert, $offset, 0);
    }

    public static function replace(string $source, string $replace, int $offset, int $length): string
    {
        $prefix = self::prefix($source, $offset);
        $suffix = self::suffix($source, $offset + $length);

        return $prefix . $replace . $suffix;
    }

    public static function prefix(string $source, int $offset): string
    {
        $result = @\substr($source, 0, $offset);

        if ($result === false) {
            return $source;
        }

        return $result;
    }

    public static function suffix(string $source, int $offset): string
    {
        $result = @\substr($source, $offset);

        if ($result === false) {
            return '';
        }

        return $result;
    }
}
