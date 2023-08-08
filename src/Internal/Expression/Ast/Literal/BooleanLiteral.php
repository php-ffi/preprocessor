<?php

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

    public function eval(): bool
    {
        return $this->value;
    }
}
