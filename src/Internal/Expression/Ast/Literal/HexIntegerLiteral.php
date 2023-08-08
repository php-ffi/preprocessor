<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Literal;

final class HexIntegerLiteral extends IntegerLiteral
{
    /**
     * @param string $value
     * @param string $suffix
     */
    public function __construct(string $value, string $suffix)
    {
        parent::__construct(\hexdec($value), $suffix);
    }
}
