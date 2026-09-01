<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Passes the diagnostics to a PSR-3 logger as soon as they are reported: the
 * severity is the level, the text is the message, the place in the file is
 * the context (`file`, `line`, `column`, `offset` and `length`).
 *
 * @link https://www.php-fig.org/psr/psr-3/ PSR-3: Logger Interface
 */
final class LoggerDiagnosticListener implements DiagnosticListenerInterface
{
    public function __construct(
        /**
         * The logger the diagnostics are passed to
         */
        private readonly LoggerInterface $logger,
    ) {}

    public function notify(Diagnostic $diagnostic): void
    {
        $position = $diagnostic->findPosition();

        $this->logger->log($this->toPsr3Severity($diagnostic->severity), $diagnostic->message, [
            'file' => $diagnostic->source->pathname,
            'line' => $position?->line,
            'column' => $position?->column,
            'offset' => $diagnostic->offset,
            'length' => $diagnostic->length,
        ]);
    }

    private function toPsr3Severity(Severity $severity): string
    {
        return match ($severity) {
            Severity::Warning => LogLevel::WARNING,
            Severity::Error => LogLevel::ERROR,
        };
    }
}
