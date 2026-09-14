<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\FileSystem\FileSystemInterface;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Runtime\SourceExecutorInterface;

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
        /**
         * The macros defined at the moment, `#define` and `#undef` change them
         */
        public readonly MacroRepository $macros,
        /**
         * The files `#include` and `#embed` search
         */
        public readonly FileSystemInterface $files,
        /**
         * Preprocesses the files `#include` includes
         */
        public readonly SourceExecutorInterface $executor,
    ) {}
}
