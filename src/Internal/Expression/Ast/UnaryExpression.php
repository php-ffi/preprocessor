<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
