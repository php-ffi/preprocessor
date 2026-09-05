<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

/**
 * Occurs when the preprocessor fails for a reason other than a defect of
 * the preprocessed source.
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
}
