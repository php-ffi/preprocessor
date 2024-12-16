<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Math;

use FFI\Preprocessor\Internal\Expression\Ast\UnaryExpression;

class NotExpression extends UnaryExpression
{
    public function eval(): bool
    {
        return !$this->value->eval();
    }
}
