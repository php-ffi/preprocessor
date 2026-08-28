<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\VirtualFileSystem;

use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Source\FileSource;

/**
 * Real directory mounted to a virtual directory: the files of the real
 * directory and of its subdirectories are seen under the virtual one, as
 * they are at the moment of the lookup. The case sensitivity of their names
 * is the one of the real file system.
 *
 * @readonly
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\FileSystem
 */
final class MountedDirectory
{
    /**
     * Prefix of the virtual pathname of the files of the directory
     */
    private readonly string $prefix;

    /**
     * Real pathname of the directory without the trailing separators
     */
    private readonly string $directory;

    /**
     * @param non-empty-string $pathname the normalized virtual pathname
     *        the directory is mounted to
     * @param non-empty-string $directory the real pathname of an existing
     *        directory
     */
    public function __construct(string $pathname, string $directory)
    {
        $this->prefix = self::formatPathname($pathname);
        $this->directory = self::formatDirectory($directory);
    }

    private static function formatPathname(string $pathname): string
    {
        if ($pathname === VirtualFileSystem::VFS_ROOT_DIRECTORY) {
            return '';
        }

        return $pathname;
    }

    private static function formatDirectory(string $directory): string
    {
        return \rtrim($directory, '/\\');
    }

    /**
     * @param non-empty-string $pathname the normalized virtual pathname
     * @return ReadableInterface|null the contents of the file, {@see null} if
     *         the directory has no file of this pathname
     */
    public function find(string $pathname): ?ReadableInterface
    {
        if ($this->prefix !== '' && !\str_starts_with($pathname, $this->prefix . VirtualFileSystem::VFS_PATH_DELIMITER)) {
            return null;
        }

        /** @var non-empty-string $real */
        $real = $this->directory . \substr($pathname, \strlen($this->prefix));

        if (!\is_file($real)) {
            return null;
        }

        return new FileSource($real);
    }
}
