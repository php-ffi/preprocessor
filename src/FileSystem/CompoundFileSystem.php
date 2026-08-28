<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

use Phplrt\Contracts\Source\FileInterface;

/**
 * File systems searched in their order: the file is the one of the first
 * file system that has it.
 *
 * @readonly
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
final class CompoundFileSystem implements FileSystemInterface
{
    public function __construct(
        /**
         * The file systems in the order of the search
         *
         * @var non-empty-list<FileSystemInterface>
         */
        private readonly array $filesystems,
    ) {}

    public function find(string $pathname, ?FileInterface $from = null): ?FileInterface
    {
        foreach ($this->filesystems as $fs) {
            $file = $fs->find($pathname, $from);

            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }
}
