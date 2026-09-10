<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler\DirectiveHandler;

/**
 * Name of a header or of a resource in `#include` and `#embed`, such as
 * `"a.h"` or `<a.h>` (6.4.7).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.7 Header names
 */
final class HeaderName
{
    public function __construct(
        /**
         * The name between the delimiters
         *
         * @var non-empty-string
         */
        public readonly string $pathname,
        /**
         * Whether the name is delimited by `<` and `>`, not by quotes
         */
        public readonly bool $isAngled,
        /**
         * Length in bytes of the name with its delimiters
         *
         * @var int<1, max>
         */
        public readonly int $length,
    ) {}
}
