<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Internal\Expression\Ast;

class CastExpression extends Expression
{
    private string $type;

    private ExpressionInterface $value;

    public function __construct(string $type, ExpressionInterface $value)
    {
        $this->type = \strtolower($type);
        $this->value = $value;
    }

    /**
     * Approximate cast result.
     */
    public function eval()
    {
        switch ($this->type) {
            case 'char':
            case 'short':
            case 'int':
            case 'long':
                return (int) $this->value->eval();
            case 'string':
                return (string) $this->value->eval();
            case 'float':
            case 'double':
                return (float) $this->value->eval();
            case 'bool':
                return (bool) $this->value->eval();
            default:
                $error = \sprintf('Can not cast %s to %s', \get_debug_type($this->value->eval()), $this->type);
                throw new \LogicException($error);
        }
    }
}
