<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class CharValue extends Value
{
    public function __construct(int $value)
    {
        parent::__construct($value);
    }

    public function eval(): int
    {
        return parent::eval();
    }

    protected static function parse(string $value): int
    {
        $value = \substr($value, 1, -1);

        return \ord($value);
    }
}
