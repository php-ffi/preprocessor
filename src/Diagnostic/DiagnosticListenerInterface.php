<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic;

/**
 * Receives the diagnostics of preprocessing as soon as they occur, while
 * the output is still being read, and decides what to do with them: the
 * preprocessing goes on after the listener returns, an exception of the
 * listener stops it.
 */
interface DiagnosticListenerInterface
{
    /**
     * @throws \Throwable in case of the listener stops the preprocessing
     */
    public function notify(Diagnostic $diagnostic): void;
}
