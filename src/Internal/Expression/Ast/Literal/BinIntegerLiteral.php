<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Literal;

final class BinIntegerLiteral extends IntegerLiteral
{
    public function __construct(string $value, string $suffix)
    {
        parent::__construct(\bindec($value), $suffix);
    }
}
