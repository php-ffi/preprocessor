<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

use FFI\Preprocessor\FileSystem\Exception\DirectoryNotFoundException;
use FFI\Preprocessor\FileSystem\Exception\InvalidPathException;
use FFI\Preprocessor\FileSystem\VirtualFileSystem\MountedDirectory;
use FFI\Preprocessor\FileSystem\VirtualFileSystem\PathNormalizer;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Source\VirtualSource;

/**
 * File system the included files are searched in. A file is searched in the
 * added files first, then in the mounted directories in the order of
 * mounting, then in the parent file system, if there is one.
 *
 * Virtual path names are case-sensitive. A virtual pathname never leaves the
 * root directory: `..` at the root stays at the root, a file outside a
 * mounted directory cannot be reached through it.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
final class VirtualFileSystem extends FileSystem implements MutableFileSystemInterface
{
    /**
     * Contents of the files by their normalized virtual path names
     *
     * @var array<non-empty-string, ReadableInterface>
     */
    private array $files = [];

    /**
     * Mounted directories in the order of mounting
     *
     * @var list<MountedDirectory>
     */
    private array $directories = [];

    /**
     * Pathname of the root directory
     *
     * @var non-empty-string
     */
    public const VFS_ROOT_DIRECTORY = '/';

    /**
     * Path delimiter char
     *
     * @var non-empty-string
     */
    public const VFS_PATH_DELIMITER = '/';

    public function __construct(
        /**
         * The file system searched for what this one does not have
         */
        private readonly ?FileSystemInterface $parent = null,
        ?PathMatcherInterface $matcher = null,
    ) {
        parent::__construct($matcher);
    }

    /**
     * @throws InvalidPathException in case of the pathname is the root
     */
    public function addFile(string $pathname, ReadableInterface $source): void
    {
        $normalized = PathNormalizer::normalize($pathname);

        if ($normalized === self::VFS_ROOT_DIRECTORY) {
            throw InvalidPathException::becausePathIsRoot($pathname);
        }

        $this->files[$normalized] = $source;
    }

    /**
     * @throws DirectoryNotFoundException in case of the directory does not exist
     */
    public function addDirectory(string $directory, string $pathname = self::VFS_ROOT_DIRECTORY): void
    {
        if ($directory === '' || !\is_dir($directory)) {
            throw DirectoryNotFoundException::becauseDirectoryIsNotFound($directory);
        }

        $this->directories[] = new MountedDirectory(PathNormalizer::normalize($pathname), $directory);
    }

    public function find(string $pathname, ?FileInterface $from = null): ?FileInterface
    {
        if ($from !== null && !$this->isAbsolute($pathname)) {
            $directory = PathNormalizer::getDirectory($from->pathname);
            $file = $this->findByNormalizedPathname(PathNormalizer::join($directory, $pathname));
        } else {
            $file = $this->findByNormalizedPathname(PathNormalizer::normalize($pathname));
        }

        return $file ?? $this->parent?->find($pathname, $from);
    }

    /**
     * @param non-empty-string $pathname the normalized virtual pathname
     */
    private function findByNormalizedPathname(string $pathname): ?FileInterface
    {
        if (isset($this->files[$pathname])) {
            return new VirtualSource($pathname, $this->files[$pathname]);
        }

        foreach ($this->directories as $directory) {
            $source = $directory->find($pathname);

            if ($source !== null) {
                return new VirtualSource($pathname, $source);
            }
        }

        return null;
    }
}
