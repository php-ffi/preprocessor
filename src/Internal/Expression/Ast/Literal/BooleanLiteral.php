<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Literal;

final class BooleanLiteral extends Literal
{
    /**
     * @var bool
     */
    private bool $value;

    /**
     * @param bool $value
     */
    public function __construct(bool $value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function eval(): bool
    {
        return $this->value;
    }
}
