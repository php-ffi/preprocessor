<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast;

use Phplrt\Contracts\Ast\NodeInterface;

interface ExpressionInterface extends NodeInterface
{
    /**
     * @return mixed
     */
    public function eval();
}
