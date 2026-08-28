<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

use FFI\Preprocessor\FileSystem\Exception\DirectoryNotFoundException;
use FFI\Preprocessor\FileSystem\Exception\InvalidPathException;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * File system configured with the files under virtual pathnames and the real
 * directories mounted to virtual ones, the search paths of the inclusions.
 */
interface MutableFileSystemInterface extends FileSystemInterface
{
    /**
     * Adds a file under a virtual pathname. A file hides the file of the same
     * pathname in the mounted directories, a file added again under the same
     * pathname replaces the previous one.
     *
     * @throws InvalidPathException in case of the pathname names the root directory
     */
    public function addFile(string $pathname, ReadableInterface $source): void;

    /**
     * Mounts a real directory to a virtual one. Mounted directories are
     * searched in the order of mounting.
     *
     * @param string $directory the real pathname of the directory
     * @param string $pathname the virtual pathname the directory is mounted to
     * @throws DirectoryNotFoundException in case of the real directory does not exist
     */
    public function addDirectory(string $directory, string $pathname = '/'): void;
}
