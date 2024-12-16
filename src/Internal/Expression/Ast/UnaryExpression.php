<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast;

abstract class UnaryExpression extends Expression
{
    protected ExpressionInterface $value;

    public function __construct(ExpressionInterface $value)
    {
        $this->value = $value;
    }
}
