<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\Exception;

/**
 * Occurs when a real file linked to the virtual file system does not exist.
 */
final class FileNotFoundException extends FileSystemException
{
    public static function becauseFileIsNotFound(string $pathname): self
    {
        $message = \sprintf('The file "%s" does not exist', \addcslashes($pathname, '"'));

        return new self($message);
    }
}
