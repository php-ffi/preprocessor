<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Executor\MacroExpander;

/**
 * The end of a replacement: the macro it was made by may be expanded again.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Macro\Executor
 */
final class Restoration
{
    public function __construct(
        public readonly string $name,
    ) {}
}
