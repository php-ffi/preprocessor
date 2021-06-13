<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Math;

use FFI\Preprocessor\Internal\Expression\Ast\UnaryExpression;

class PrefixDecrement extends UnaryExpression
{
    /**
     * @return int|float
     */
    public function eval()
    {
        return $this->value->eval() - 1;
    }
}
