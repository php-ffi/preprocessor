<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

use Phplrt\Contracts\Source\FileInterface;

/**
 * File system the preprocessor includes the files from.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
interface FileSystemInterface
{
    /**
     * Finds the file of a header name: in the directory of the including
     * file only, if it is given, or from the root. An absolute pathname is
     * searched from the root only.
     *
     * @param FileInterface|null $from the including file
     * @return FileInterface|null the file, {@see null} if there is none
     */
    public function find(string $pathname, ?FileInterface $from = null): ?FileInterface;
}
