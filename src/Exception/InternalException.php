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
    private function __construct(string $message, ?\Throwable $reason = null)
    {
        parent::__construct($message, 0, $reason);
    }

    public static function becauseInternalErrorOccurs(string $message, ?\Throwable $prev = null): self
    {
        return new self($message, reason: $prev);
    }

    private static function escape(string $message): string
    {
        return \addcslashes($message, '"');
    }

    /**
     * @param \Throwable $reason the failure reported by the factory of the sources
     */
    public static function becauseSourceIsNotCreatable(\Throwable $reason): self
    {
        $message = \sprintf('The source cannot be created: "%s"', self::escape($reason->getMessage()));

        return new self($message, $reason);
    }

    /**
     * @param \Throwable $reason the failure reported by the file
     */
    public static function becauseSourceIsNotReadable(FileInterface $source, \Throwable $reason): self
    {
        $message = \sprintf(
            'The source "%s" cannot be read: "%s"',
            self::escape($source->pathname),
            self::escape($reason->getMessage()),
        );

        return new self($message, $reason);
    }
}
