<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Exception;

use FFI\Preprocessor\Exception\PreprocessorException;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Occurs when a file cannot be read, such as a file that has been removed
 * while it is preprocessed.
 */
final class UnreadableFileException extends PreprocessorException
{
    /**
     * @param \Throwable $reason the failure reported by the file
     */
    public static function becauseReadFails(FileInterface $source, \Throwable $reason): self
    {
        $message = \sprintf(
            'The source "%s" cannot be read: "%s"',
            \addcslashes($source->pathname, '"'),
            \addcslashes($reason->getMessage(), '"'),
        );

        return new self($message, 0, $reason);
    }
}
