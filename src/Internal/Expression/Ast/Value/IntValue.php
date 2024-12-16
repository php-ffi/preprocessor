<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class IntValue extends Value
{
    public function __construct(int $value)
    {
        parent::__construct($value);
    }

    protected static function parse(string $value): int
    {
        return (int) $value;
    }

    public function eval(): int
    {
        return parent::eval();
    }
}
