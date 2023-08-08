<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast;

abstract class BinaryExpression extends Expression
{
    /**
     * @var ExpressionInterface
     */
    protected ExpressionInterface $a;

    /**
     * @var ExpressionInterface
     */
    protected ExpressionInterface $b;

    /**
     * @param ExpressionInterface $a
     * @param ExpressionInterface $b
     */
    public function __construct(ExpressionInterface $a, ExpressionInterface $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}
