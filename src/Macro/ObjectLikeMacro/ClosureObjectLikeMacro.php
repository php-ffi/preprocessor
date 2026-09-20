<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\ObjectLikeMacro;

use FFI\Preprocessor\Macro\Exception\InvalidMacroException;
use FFI\Preprocessor\Macro\MacroContext;
use FFI\Preprocessor\Macro\ObjectLikeMacro;

/**
 * Macro without a parameter list implemented by PHP code: the closure
 * returns the text the name is replaced by, each time the name is replaced
 * (6.10.5p9).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.5 Macro replacement
 */
final class ClosureObjectLikeMacro extends ObjectLikeMacro
{
    public function __construct(
        /**
         * Returns the text the name is replaced by
         *
         * @var \Closure(): (scalar|\Stringable|null)
         */
        private readonly \Closure $body,
    ) {}

    public static function isSupported(\Closure $replacement): bool
    {
        try {
            $reflection = new \ReflectionFunction($replacement);
        } catch (\Throwable) {
            return false;
        }

        return $reflection->getNumberOfParameters() === 0;
    }

    /**
     * @param \Closure(): (scalar|\Stringable|null) $closure
     * @throws InvalidMacroException in case of the closure has parameters
     */
    public static function createFromClosure(\Closure $closure): self
    {
        if (!self::isSupported($closure)) {
            throw InvalidMacroException::becauseObjectLikeMacroHasParameters();
        }

        return new self($closure);
    }

    public function replace(array $arguments, MacroContext $context): string
    {
        return (string) ($this->body)();
    }
}
