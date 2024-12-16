<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast\Value;

use FFI\Preprocessor\Internal\Expression\Ast\Expression;
use Phplrt\Contracts\Lexer\TokenInterface;

/**
 * @internal
 */
abstract class Value extends Expression
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    protected static function parse(string $value): string
    {
        return $value;
    }

    /**
     * @return static
     */
    public static function fromToken(TokenInterface $token): self
    {
        return new static(static::parse($token->getValue()));
    }

    public function eval()
    {
        return $this->value;
    }
}
