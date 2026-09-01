<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;

final class NullDiagnosticListener implements DiagnosticListenerInterface
{
    public function notify(Diagnostic $diagnostic): void
    {
        // noop
    }
}
