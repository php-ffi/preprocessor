<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;

final class CompoundListener implements DiagnosticListenerInterface
{
    public function __construct(
        /**
         * @var non-empty-list<DiagnosticListenerInterface>
         */
        private readonly array $listeners,
    ) {}

    public function notify(Diagnostic $diagnostic): void
    {
        foreach ($this->listeners as $listener) {
            $listener->notify($diagnostic);
        }
    }
}
