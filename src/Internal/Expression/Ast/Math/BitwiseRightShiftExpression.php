<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Math;

use FFI\Preprocessor\Internal\Expression\Ast\BinaryExpression;

class BitwiseRightShiftExpression extends BinaryExpression
{
    public function eval(): int
    {
        return $this->a->eval() >> $this->b->eval();
    }
}
