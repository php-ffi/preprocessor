<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Node;

/**
 * Base of the nodes of the expression: the place of the node.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
abstract class Expression implements ExpressionInterface
{
    public function __construct(
        /**
         * Offset of the first byte of the node in the text of the expression
         *
         * @var int<0, max>
         */
        public readonly int $offset = 0,
    ) {}
}
