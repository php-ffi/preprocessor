<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Comparison;

final class LessThan extends Comparison
{
    public function eval(): bool
    {
        return $this->a->eval() < $this->b->eval();
    }
}
