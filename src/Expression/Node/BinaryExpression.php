<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node;

/**
 * Base of the binary operators (6.5.5 to 6.5.14 and 6.5.17): the two
 * operands. The operator is the class of the node.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.5 Expressions
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
abstract class BinaryExpression extends Expression
{
    /**
     * @param int<0, max> $offset
     */
    final public function __construct(
        /**
         * The left operand
         */
        public readonly ExpressionInterface $left,
        /**
         * The right operand
         */
        public readonly ExpressionInterface $right,
        int $offset = 0,
    ) {
        parent::__construct($offset);
    }
}
