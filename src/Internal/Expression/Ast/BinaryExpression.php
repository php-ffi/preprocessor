<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast;

abstract class BinaryExpression extends Expression
{
    protected ExpressionInterface $a;

    protected ExpressionInterface $b;

    public function __construct(ExpressionInterface $a, ExpressionInterface $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}
