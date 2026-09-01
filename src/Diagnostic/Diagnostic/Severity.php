<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\Diagnostic;

/**
 * How much a diagnostic matters. The listener decides what to do with it:
 * the default one stops the preprocessing at an error. A failure the
 * preprocessing cannot go on after, such as a malformed `#if`, is not a
 * diagnostic: it is an exception.
 */
enum Severity: string
{
    /**
     * The source is probably wrong, but the preprocessing goes on: a
     * `#warning`, a macro redefined in a different way.
     */
    case Warning = 'warning';

    /**
     * The source is wrong, the construct is skipped: an `#error`, a
     * directive of an unknown name, a file that is not found.
     */
    case Error = 'error';
}
