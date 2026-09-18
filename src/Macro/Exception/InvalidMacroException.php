<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Exception;

/**
 * Occurs when a macro cannot be defined as asked: the name is not an
 * identifier, or the replacement is not what the macro is made of.
 */
final class InvalidMacroException extends MacroException
{
    public static function becauseNameIsInvalid(string $name): self
    {
        $message = \sprintf('Macro name "%s" is not a valid macro identifier', \addcslashes($name, '"'));

        return new self($message);
    }

    public static function becauseReplacementIsInvalid(string $type): self
    {
        $message = \sprintf('Macro replacement of type "%s" is not supported', \addcslashes($type, '"'));

        return new self($message);
    }

    public static function becauseObjectLikeMacroHasParameters(): self
    {
        return new self('Object-like macro has no parameters to take the closure arguments from');
    }
}
