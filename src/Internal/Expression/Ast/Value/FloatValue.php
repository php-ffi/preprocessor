<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class FloatValue extends Value
{
    /**
     * @param int $value
     */
    public function __construct(int $value)
    {
        parent::__construct($value);
    }

    /**
     * @return float
     */
    public function eval(): float
    {
        return parent::eval();
    }

    /**
     * @param string $value
     * @return float
     */
    protected static function parse(string $value): float
    {
        return (float)$value;
    }
}
