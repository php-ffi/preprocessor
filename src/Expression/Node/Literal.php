<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node;

/**
 * Base of the constants of the expression: the text they are written as.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4 Constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
abstract class Literal extends Expression
{
    /**
     * @param int<0, max> $offset
     */
    public function __construct(
        /**
         * The constant exactly as written, such as `0x1Fu` or `L'a'`
         *
         * @var non-empty-string
         */
        public readonly string $raw,
        int $offset = 0,
    ) {
        parent::__construct($offset);
    }
}
