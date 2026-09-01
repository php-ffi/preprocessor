<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;

/**
 * Stops the preprocessing at an error with an exception and skips the
 * warnings, the listener of the preprocessor by default.
 */
final class ThrowingDiagnosticListener implements DiagnosticListenerInterface
{
    /**
     * @throws \RuntimeException in case of the diagnostic is an error
     */
    public function notify(Diagnostic $diagnostic): void
    {
        match ($diagnostic->severity) {
            Severity::Error => $this->onError($diagnostic),
            // @phpstan-ignore-next-line : Allow complete match arms
            Severity::Warning => $this->onWarning($diagnostic),
            default => $this->onNotice($diagnostic),
        };
    }

    private function onNotice(Diagnostic $diagnostic): void
    {
        // noop
    }

    private function onWarning(Diagnostic $diagnostic): void
    {
        \trigger_error($diagnostic->message, \E_USER_WARNING);
    }

    private function onError(Diagnostic $diagnostic): void
    {
        throw new \RuntimeException($diagnostic->message);
    }
}
