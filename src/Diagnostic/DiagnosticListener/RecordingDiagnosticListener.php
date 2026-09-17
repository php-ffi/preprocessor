<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;

/**
 * Remembers the diagnostics of one run and passes them on to the listener
 * the user configured.
 */
final class RecordingDiagnosticListener implements DiagnosticListenerInterface
{
    /**
     * @var list<Diagnostic>
     */
    private array $diagnostics = [];

    public function __construct(
        private readonly DiagnosticListenerInterface $listener,
    ) {}

    /**
     * Returns the diagnostics received so far, the one that stopped the preprocessing included.
     *
     * @return list<Diagnostic>
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @throws \Throwable in case of the listener stops the preprocessing
     */
    public function notify(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;

        $this->listener->notify($diagnostic);
    }
}
