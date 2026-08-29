<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\ExpressionEvaluator;

/**
 * Value of a node during the evaluation: the number of the driver and the
 * signedness of its type.
 *
 * @template TNumber of mixed
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Expression\Evaluator
 */
final class Operand
{
    public function __construct(
        /**
         * The 64 bits of the value
         *
         * @var TNumber
         */
        public readonly mixed $number,
        /**
         * Whether the type is `uintmax_t`
         */
        public readonly bool $isUnsigned,
    ) {}
}
