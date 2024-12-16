<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class UnrecognizedDefineValue extends Value
{
    public function __construct(string $value)
    {
        parent::__construct($value);
    }

    public function eval(): bool
    {
        return false;
    }
}
