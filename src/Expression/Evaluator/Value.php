<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator;

/**
 * Value of the controlling expression of a conditional directive: an
 * integer of the type `intmax_t` or `uintmax_t` (6.10.2p13).
 *
 * @readonly
 */
final class Value
{
    public readonly bool $isTrue;

    public function __construct(
        /**
         * The value in decimal digits, with a minus of a negative one
         *
         * @var numeric-string
         */
        public readonly string $decimal,
        /**
         * Whether the type is `uintmax_t`
         */
        public readonly bool $isUnsigned = false,
    ) {
        $this->isTrue = $this->isEvaluatesToTrue();
    }

    /**
     * Whether the value is not zero: the group of the directive is taken
     * (6.10.2p12).
     */
    private function isEvaluatesToTrue(): bool
    {
        return $this->decimal !== '0';
    }
}
