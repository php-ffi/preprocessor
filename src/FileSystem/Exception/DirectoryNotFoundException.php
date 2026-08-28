<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\Exception;

/**
 * Occurs when a real directory mounted to the virtual file system does not
 * exist.
 */
final class DirectoryNotFoundException extends FileSystemException
{
    public static function becauseDirectoryIsNotFound(string $directory): self
    {
        $message = \sprintf('The directory "%s" does not exist', \addcslashes($directory, '"'));

        return new self($message);
    }
}
