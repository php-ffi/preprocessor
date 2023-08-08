<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast;

abstract class UnaryExpression extends Expression
{
    /**
     * @var ExpressionInterface
     */
    protected ExpressionInterface $value;

    /**
     * @param ExpressionInterface $value
     */
    public function __construct(ExpressionInterface $value)
    {
        $this->value = $value;
    }
}
