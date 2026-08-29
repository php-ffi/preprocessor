<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node;

/**
 * Conditional operator, `a ? b : c` (6.5.16): only one of the branches is
 * evaluated.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.5.16 Conditional operator
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class ConditionalExpression extends Expression
{
    /**
     * @param int<0, max> $offset
     */
    public function __construct(
        /**
         * The first operand, compared to zero
         */
        public readonly ExpressionInterface $condition,
        /**
         * The operand evaluated in case of the condition is not zero
         */
        public readonly ExpressionInterface $then,
        /**
         * The operand evaluated in case of the condition is zero
         */
        public readonly ExpressionInterface $else,
        int $offset = 0,
    ) {
        parent::__construct($offset);
    }
}
