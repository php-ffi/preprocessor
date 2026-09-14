<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\Exception;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Exception\RuntimeException;

/**
 * Occurs when a diagnostic of an error stops the preprocessing, such as the
 * file of `#include` that is not found (6.10.3).
 */
final class DiagnosticException extends RuntimeException
{
    public static function becauseErrorIsReported(Diagnostic $diagnostic): self
    {
        return new self($diagnostic->message, $diagnostic->source, $diagnostic->offset, $diagnostic->length);
    }
}
