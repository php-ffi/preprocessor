<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class NullValue extends Value
{
    /**
     * NullValue constructor.
     */
    public function __construct()
    {
        parent::__construct(null);
    }

    /**
     * @return void
     */
    public function eval(): void
    {
    }
}
