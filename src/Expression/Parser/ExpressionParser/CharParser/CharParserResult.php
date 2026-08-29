<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Parser\ExpressionParser\CharParser;

use FFI\Preprocessor\Expression\Node\Literal\CharLiteral\CharPrefix;

/**
 * Character constant parsed out of its text.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Expression\Parser
 */
final class CharParserResult
{
    public function __construct(
        /**
         * The encoding prefix of the constant
         */
        public readonly CharPrefix $prefix,
        /**
         * The characters between the quotes with the escape sequences
         * expanded
         */
        public readonly string $value,
    ) {}
}
