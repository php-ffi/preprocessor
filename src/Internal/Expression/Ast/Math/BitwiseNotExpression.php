<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Math;

use FFI\Preprocessor\Internal\Expression\Ast\UnaryExpression;

class BitwiseNotExpression extends UnaryExpression
{
    public function eval(): int
    {
        return ~$this->value->eval();
    }
}
