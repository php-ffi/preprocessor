<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use FFI\Preprocessor\Diagnostic\Exception\DiagnosticException;

/**
 * Stops the preprocessing at an error with an exception and skips the
 * warnings, the listener of the preprocessor by default.
 */
final class ThrowingDiagnosticListener implements DiagnosticListenerInterface
{
    /**
     * @throws DiagnosticException in case of the diagnostic is an error
     */
    public function notify(Diagnostic $diagnostic): void
    {
        if ($diagnostic->severity === Severity::Error) {
            throw DiagnosticException::becauseErrorIsReported($diagnostic);
        }
    }
}
