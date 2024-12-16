<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Logical;

use FFI\Preprocessor\Internal\Expression\Ast\BinaryExpression;

final class BitwiseXorExpression extends BinaryExpression
{
    public function eval(): int
    {
        return $this->a->eval() ^ $this->b->eval();
    }
}
