<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

use Phplrt\Contracts\Source\FileInterface;

/**
 * Occurs when the preprocessor fails for a reason other than a defect of
 * the preprocessed source, such as a file that cannot be read.
 */
final class InternalException extends PreprocessorException
{
    private function __construct(string $message, \Throwable $reason)
    {
        parent::__construct($message, 0, $reason);
    }

    /**
     * @param \Throwable $reason the failure reported by the file
     */
    public static function becauseSourceIsNotReadable(FileInterface $source, \Throwable $reason): self
    {
        $message = \sprintf(
            'The source "%s" cannot be read: "%s"',
            \addcslashes($source->pathname, '"'),
            \addcslashes($reason->getMessage(), '"'),
        );

        return new self($message, $reason);
    }
}
