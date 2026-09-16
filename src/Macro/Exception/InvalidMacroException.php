<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Exception;

/**
 * Occurs when a macro cannot be defined as asked: the name is not an
 * identifier.
 */
final class InvalidMacroException extends MacroException
{
    public static function becauseNameIsInvalid(string $name): self
    {
        $message = \sprintf('Macro name "%s" is not a valid macro identifier', \addcslashes($name, '"'));

        return new self($message);
    }
}
