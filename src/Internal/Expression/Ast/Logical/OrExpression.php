<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Logical;

use FFI\Preprocessor\Internal\Expression\Ast\BinaryExpression;

final class OrExpression extends BinaryExpression
{
    /**
     * @return bool
     */
    public function eval(): bool
    {
        return $this->a->eval() || $this->b->eval();
    }
}
