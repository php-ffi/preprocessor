<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive;

/**
 * Directive handler that is executed while the output is disabled too, such
 * as the handlers of `#if` and `#endif` that keep track of the nesting of the
 * skipped conditional sections (6.10.2). The other handlers are not executed
 * in the skipped groups.
 */
interface OutputIndependentDirectiveHandlerInterface extends DirectiveHandlerInterface {}
