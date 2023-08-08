<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class IntValue extends Value
{
    /**
     * @param int $value
     */
    public function __construct(int $value)
    {
        parent::__construct($value);
    }

    /**
     * @param string $value
     * @return int
     */
    protected static function parse(string $value): int
    {
        return (int)$value;
    }

    /**
     * @return int
     */
    public function eval(): int
    {
        return parent::eval();
    }
}
