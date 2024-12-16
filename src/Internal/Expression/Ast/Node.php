<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast;

use Phplrt\Contracts\Ast\NodeInterface;

/**
 * @internal
 */
abstract class Node implements NodeInterface
{
    public function getIterator(): \Traversable
    {
        return new \EmptyIterator();
    }
}
