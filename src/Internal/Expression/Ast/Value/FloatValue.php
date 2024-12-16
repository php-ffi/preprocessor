<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class FloatValue extends Value
{
    public function __construct(int $value)
    {
        parent::__construct($value);
    }

    public function eval(): float
    {
        return parent::eval();
    }

    protected static function parse(string $value): float
    {
        return (float) $value;
    }
}
