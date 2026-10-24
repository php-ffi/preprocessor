<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

use FFI\Preprocessor\FileSystem\Exception\DirectoryNotFoundException;
use FFI\Preprocessor\FileSystem\Exception\FileNotFoundException;
use FFI\Preprocessor\FileSystem\Exception\InvalidPathException;
use FFI\Preprocessor\FileSystem\VirtualFileSystem\MountedDirectory;
use FFI\Preprocessor\FileSystem\VirtualFileSystem\PathNormalizer;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Contracts\Source\SourceFactoryInterface;
use Phplrt\Source\FileSource;
use Phplrt\Source\SourceFactory;
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
 * ```php
 * $files = new VirtualFileSystem();
 *
 * $files->add('config.h', '#define VERSION 2');   // a file of the given contents
 * $files->link('lib/gl.h', '/usr/include/GL/gl.h'); // a real file under a virtual name
 * $files->mount('/usr/include', 'sys');             // a real directory under a virtual one
 *
 * $files->find('config.h')?->pathname;     // "/config.h"
 * $files->find('sys/types.h')?->pathname;  // "/sys/types.h"
 * ```
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
final class VirtualFileSystem extends FileSystem
{
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

    private SourceFactoryInterface $sources;

    public function __construct(
        /**
         * The file system searched for what this one does not have
         */
        private readonly ?FileSystemInterface $parent = null,
        ?PathMatcherInterface $matcher = null,
        ?SourceFactoryInterface $sources = null,
    ) {
        $this->sources = self::createSourceFactory($sources);

        parent::__construct($matcher);
    }

    private static function createSourceFactory(?SourceFactoryInterface $sources): SourceFactoryInterface
    {
        if ($sources !== null) {
            return $sources;
        }

        return SourceFactory::createDefault();
    }

    /**
     * @throws InvalidPathException in case of the pathname names the root directory
     */
    private function attach(string $pathname, ReadableInterface $source): void
    {
        $normalized = PathNormalizer::normalize($pathname);

        if ($normalized === self::VFS_ROOT_DIRECTORY) {
            throw InvalidPathException::becausePathIsRoot($pathname);
        }

        $this->files[$normalized] = $source;
    }

    /**
     * Adds a file of the given contents under a virtual pathname. A file of
     * the same pathname that is already added is replaced.
     *
     * The pathname is delimited by `/` or `\`, `.` and `..` are resolved, so
     * `sys\a.h`, `./sys/a.h` and `/sys/a.h` name the same file.
     *
     * The source is anything the source factory creates a source of: the text
     * itself, an `\SplFileInfo`, a stream, a ready source.
     *
     * ```php
     * $files->add('config.h', '#define VERSION 2');
     * $files->add('sys/types.h', new \SplFileInfo('/usr/include/sys/types.h'));
     * ```
     *
     * @throws InvalidPathException in case of the pathname names the root directory
     * @throws \Throwable in case of the source cannot be created
     */
    public function add(string $pathname, mixed $source): void
    {
        $this->attach($pathname, $this->sources->create($source));
    }

    /**
     * Adds a real file under a virtual pathname. The contents are read at the
     * moment of the inclusion, not of the linking.
     *
     * ```php
     * $files->link('gl.h', '/usr/include/GL/gl.h');
     *
     * $files->find('gl.h')?->pathname;  // "/gl.h"
     * ```
     *
     * @throws InvalidPathException in case of the pathname names the root directory
     * @throws FileNotFoundException in case of the target file does not exist
     */
    public function link(string $pathname, string $target): void
    {
        if ($target === '' || !\is_file($target)) {
            throw FileNotFoundException::becauseFileIsNotFound($target);
        }

        $this->attach($pathname, FileSource::createFromPathname($target));
    }

    /**
     * Mounts a real directory to a virtual one. The files of the directory and
     * of its subdirectories are seen under the virtual directory as they are
     * at the moment of the lookup. A directory mounted earlier is searched
     * first.
     *
     * ```php
     * $files->mount('/usr/include');              // "/usr/include/stdio.h" is "stdio.h"
     * $files->mount('/opt/sdk/include', 'sdk');   // "/opt/sdk/include/a.h" is "sdk/a.h"
     * ```
     *
     * @throws DirectoryNotFoundException in case of the directory does not exist
     */
    public function mount(string $directory, string $pathname = self::VFS_ROOT_DIRECTORY): void
    {
        if ($directory === '' || !\is_dir($directory)) {
            throw DirectoryNotFoundException::becauseDirectoryIsNotFound($directory);
        }

        $this->directories[] = new MountedDirectory(PathNormalizer::normalize($pathname), $directory);
    }

    /**
     * Finds the file of a header name. A name relative to the including file
     * is searched in the directory of that file, any other name from the root.
     * What is not found here is searched in the parent file system.
     *
     * ```php
     * $files->add('sys/a.h', '');
     * $files->add('sys/b.h', '');
     *
     * $files->find('sys/a.h');                         // "/sys/a.h"
     * $files->find('a.h');                             // null
     * $files->find('a.h', $files->find('sys/b.h'));    // "/sys/a.h"
     * $files->find('/sys/a.h', $files->find('sys/b.h')); // "/sys/a.h"
     * $files->find('../../sys/a.h');                   // "/sys/a.h", the root has no parent
     * ```
     */
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
