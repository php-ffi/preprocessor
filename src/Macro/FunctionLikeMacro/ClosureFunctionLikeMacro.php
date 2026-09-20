<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\FunctionLikeMacro;

use FFI\Preprocessor\Macro\FunctionLikeMacro;
use FFI\Preprocessor\Macro\MacroContext;

/**
 * Function-like macro implemented by PHP code: the closure gets the arguments
 * and returns the text the invocation is replaced by.
 */
final class ClosureFunctionLikeMacro extends FunctionLikeMacro
{
    /**
     * @param int<0, max> $params
     */
    public function __construct(
        /**
         * Returns the text the invocation is replaced by
         *
         * @var \Closure(string ...): (scalar|\Stringable|null)
         */
        private readonly \Closure $body,
        int $params,
        bool $isVariadic = false,
    ) {
        parent::__construct($params, $isVariadic);
    }

    /**
     * Creates the macro with the parameters of the closure.
     *
     * @param \Closure(string ...): (scalar|\Stringable|null) $closure
     */
    public static function createFromClosure(\Closure $closure): self
    {
        try {
            $reflection = new \ReflectionFunction($closure);

            $params = $reflection->getNumberOfParameters();
            $isVariadic = $reflection->isVariadic();
        } catch (\Throwable) {
            $params = 0;
            $isVariadic = false;
        }

        return new self($closure, $params, $isVariadic);
    }

    public function replace(array $arguments, MacroContext $context): string
    {
        return (string) ($this->body)(...$arguments);
    }
}
