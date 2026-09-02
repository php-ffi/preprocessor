<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;

/**
 * What a directive handler may do with the preprocessing.
 */
final class DirectiveContext
{
    public function __construct(
        /**
         * The open conditional sections, `#if` and `#endif` change them
         */
        public readonly OutputStack $output,
        /**
         * Receives the messages of `#error` and `#warning`
         */
        public readonly DiagnosticListenerInterface $diagnostic,
    ) {}
}
