<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Source\FileSource;

/**
 * Files of the operating system as they are: an absolute pathname of the
 * operating system, or a pathname relative to the directory of the
 * including file of the operating system. A name with nothing to be
 * relative to names no file here.
 *
 * @readonly
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
final class RealFileSystem extends FileSystem
{
    public function __construct(
        /**
         * The file system searched for what this one does not have
         */
        private readonly ?FileSystemInterface $parent = null,
        ?PathMatcherInterface $matcher = null,
    ) {
        parent::__construct($matcher);
    }

    public function find(string $pathname, ?FileInterface $from = null): ?FileInterface
    {
        return $this->findFile($pathname, $from) ?? $this->parent?->find($pathname, $from);
    }

    private function findFile(string $pathname, ?FileInterface $from): ?FileSource
    {
        if ($this->isAbsolute($pathname)) {
            return self::findRealFile($pathname);
        }

        if ($pathname !== '' && $from instanceof FileSource) {
            return self::findRealFile(\dirname($from->pathname) . \DIRECTORY_SEPARATOR . $pathname);
        }

        return null;
    }

    private static function findRealFile(string $pathname): ?FileSource
    {
        $real = \realpath($pathname);

        if ($real === false || !\is_file($real)) {
            return null;
        }

        return new FileSource($real);
    }
}
