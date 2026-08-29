<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Parser\ExpressionParser\IntSuffixParser;

use FFI\Preprocessor\Expression\Node\Literal\IntLiteral\IntTypeSuffix;

/**
 * Suffix of an integer constant parsed out of its text.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Expression\Parser
 */
final class IntSuffixParserResult
{
    public function __construct(
        /**
         * The type suffix, {@see null} in case of there is none
         */
        public readonly ?IntTypeSuffix $type,
        /**
         * Whether the constant has the imaginary suffix of GCC
         */
        public readonly bool $isImaginary,
        /**
         * The offset of the suffix in the constant, its length in case of
         * there is no suffix
         *
         * @var int<0, max>
         */
        public readonly int $offset,
    ) {}
}
