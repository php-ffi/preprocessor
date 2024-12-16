<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class BoolValue extends Value
{
    public function __construct(bool $value)
    {
        parent::__construct($value);
    }

    public function eval(): bool
    {
        return parent::eval();
    }

    protected static function parse(string $value): bool
    {
        return $value === 'true';
    }
}
