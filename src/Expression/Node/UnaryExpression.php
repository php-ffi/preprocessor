<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node;

/**
 * Base of the unary operators (6.5.4.3): the operand. The operator is the
 * class of the node.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.5.4.3 Unary arithmetic operators
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
abstract class UnaryExpression extends Expression
{
    /**
     * @param int<0, max> $offset
     */
    final public function __construct(
        /**
         * The expression the operator is applied to
         */
        public readonly ExpressionInterface $operand,
        int $offset = 0,
    ) {
        parent::__construct($offset);
    }
}
