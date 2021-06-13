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
final class BoolValue extends Value
{
    /**
     * @param bool $value
     */
    public function __construct(bool $value)
    {
        parent::__construct($value);
    }

    /**
     * @return bool
     */
    public function eval(): bool
    {
        return parent::eval();
    }

    /**
     * @param string $value
     * @return bool
     */
    protected static function parse(string $value): bool
    {
        return $value === 'true';
    }
}
