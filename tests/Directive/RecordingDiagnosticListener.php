<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Directive;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;

final class RecordingDiagnosticListener implements DiagnosticListenerInterface
{
    /**
     * @var list<Diagnostic>
     */
    public array $diagnostics = [];

    public function notify(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }
}
