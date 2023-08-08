<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Runtime;

/**
 * @internal
 */
final class Source
{
    /**
     * @param string $source
     * @param string $insert
     * @param int $offset
     * @return string
     */
    public static function insert(string $source, string $insert, int $offset): string
    {
        return self::replace($source, $insert, $offset, 0);
    }

    /**
     * @param string $source
     * @param string $replace
     * @param int $offset
     * @param int $length
     * @return string
     */
    public static function replace(string $source, string $replace, int $offset, int $length): string
    {
        $prefix = self::prefix($source, $offset);
        $suffix = self::suffix($source, $offset + $length);

        return $prefix . $replace . $suffix;
    }

    /**
     * @param string $source
     * @param int $offset
     * @return string
     */
    public static function prefix(string $source, int $offset): string
    {
        $result = @\substr($source, 0, $offset);

        if ($result === false) {
            return $source;
        }

        return $result;
    }

    /**
     * @param string $source
     * @param int $offset
     * @return string
     */
    public static function suffix(string $source, int $offset): string
    {
        $result = @\substr($source, $offset);

        if ($result === false) {
            return '';
        }

        return $result;
    }
}
