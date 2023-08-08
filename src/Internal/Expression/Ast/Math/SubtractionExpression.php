<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Math;

use FFI\Preprocessor\Internal\Expression\Ast\BinaryExpression;

class SubtractionExpression extends BinaryExpression
{
    /**
     * @return mixed
     */
    public function eval()
    {
        return $this->a->eval() - $this->b->eval();
    }
}
