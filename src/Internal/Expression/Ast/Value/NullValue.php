<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

/**
 * @internal
 */
final class NullValue extends Value
{
    /**
     * NullValue constructor.
     */
    public function __construct()
    {
        parent::__construct(null);
    }

    public function eval(): void {}
}
