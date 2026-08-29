<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node\Literal;

use FFI\Preprocessor\Expression\Node\Literal;
use FFI\Preprocessor\Expression\Node\Literal\CharLiteral\CharPrefix;

/**
 * Character constant, such as `'a'`, `'\n'` or `u8'\xFF'`.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.4.5 Character constants
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class CharLiteral extends Literal
{
    /**
     * @param non-empty-string $raw
     * @param int<0, max> $offset
     */
    public function __construct(
        /**
         * The encoding prefix, which tells the type of the constant
         */
        public readonly CharPrefix $prefix,
        /**
         * The characters between the quotes with the escape sequences
         * expanded: a universal character name, and any escape sequence of a
         * wide constant, in UTF-8, an octal or a hexadecimal escape sequence
         * of the other ones as a byte, a value that does not fit as U+FFFD
         */
        public readonly string $value,
        string $raw,
        int $offset = 0,
    ) {
        parent::__construct($raw, $offset);
    }
}
