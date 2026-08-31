<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\VirtualFileSystem;

use FFI\Preprocessor\FileSystem\VirtualFileSystem;

/**
 * Brings a virtual pathname to its single spelling
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\FileSystem
 *
 * @readonly
 */
final class PathNormalizer
{
    /**
     * List of any supported path delimiters
     *
     * @var non-empty-list<non-empty-string>
     */
    private const SUPPORTED_PATH_DELIMITERS = [
        '/',
        '\\',
    ];

    /**
     * @return ($pathname is non-empty-string ? non-empty-string : string)
     */
    private static function canonical(string $pathname): string
    {
        return \str_replace(self::SUPPORTED_PATH_DELIMITERS, VirtualFileSystem::VFS_PATH_DELIMITER, $pathname);
    }

    private static function replaceRelativeSequences(string $pathname): string
    {
        $result = [];

        foreach (\explode(VirtualFileSystem::VFS_PATH_DELIMITER, $pathname) as $name) {
            if ($name === '' || $name === '.') {
                continue;
            }

            if ($name === '..') {
                \array_pop($result);

                continue;
            }

            $result[] = $name;
        }

        return VirtualFileSystem::VFS_ROOT_DIRECTORY
            . \implode(VirtualFileSystem::VFS_PATH_DELIMITER, $result);
    }

    /**
     * @return non-empty-string
     */
    public static function normalize(string $pathname): string
    {
        $result = self::replaceRelativeSequences(
            self::canonical($pathname),
        );

        if ($result === '') {
            return VirtualFileSystem::VFS_ROOT_DIRECTORY;
        }

        return $result;
    }

    /**
     * Normalizes the pathname relative to the directory.
     *
     * @return non-empty-string
     */
    public static function join(string $directory, string $pathname): string
    {
        return self::normalize(match (true) {
            $directory === '' => $pathname,
            $pathname === '' => $directory,
            default => $directory . VirtualFileSystem::VFS_PATH_DELIMITER . $pathname,
        });
    }

    /**
     * @return non-empty-string the normalized pathname of the parent directory
     */
    public static function getDirectory(string $pathname): string
    {
        return self::normalize(\dirname(self::normalize($pathname)));
    }
}
