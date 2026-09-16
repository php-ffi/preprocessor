<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro;

use FFI\Preprocessor\Macro\FunctionLikeMacro;

/**
 * Function-like macro implemented by PHP code: the closure gets the arguments
 * and returns the text the invocation is replaced by.
 */
final class ClosureFunctionLikeMacro extends FunctionLikeMacro
{
    /**
     * @param int<0, max> $args
     */
    public function __construct(
        /**
         * Returns the text the invocation is replaced by
         *
         * @var \Closure(string ...): (scalar|\Stringable|null)
         */
        private readonly \Closure $body,
        int $args,
        bool $isVariadic = false,
    ) {
        parent::__construct($args, $isVariadic);
    }

    /**
     * Creates the macro with the parameters of the closure.
     *
     * @param \Closure(string ...): (scalar|\Stringable|null) $closure
     */
    public static function createFromClosure(\Closure $closure): self
    {
        $reflection = new \ReflectionFunction($closure);

        $args = $reflection->getNumberOfParameters();
        \assert($args >= 0);

        return new self($closure, $args, $reflection->isVariadic());
    }

    public function replace(array $arguments): string
    {
        return (string) ($this->body)(...$arguments);
    }
}
