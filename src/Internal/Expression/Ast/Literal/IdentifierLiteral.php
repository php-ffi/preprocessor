<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Literal;

final class IdentifierLiteral extends Literal
{
    public function eval(): int
    {
        return 0;
    }
}
