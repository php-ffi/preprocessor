<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\Exception;

/**
 * Occurs when the virtual file system is given a pathname that cannot name
 * what it is meant to, such as a file at the root directory.
 */
final class InvalidPathException extends FileSystemException
{
    public static function becausePathIsRoot(string $pathname): self
    {
        $message = \sprintf('The file pathname "%s" names the root directory', \addcslashes($pathname, '"'));

        return new self($message);
    }
}
