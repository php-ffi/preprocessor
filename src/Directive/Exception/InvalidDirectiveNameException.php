<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\PreprocessorException;

/**
 * Occurs when a directive handler cannot be registered under the name: the
 * name is not an identifier or is taken.
 */
final class InvalidDirectiveNameException extends PreprocessorException
{
    public static function becauseNameIsNotIdentifier(string $name): self
    {
        $message = \sprintf('Directive name "%s" is not an identifier', \addcslashes($name, '"'));

        return new self($message);
    }

    public static function becauseNameIsTaken(string $name): self
    {
        $message = \sprintf(
            'A handler of the "#%s" directive is already added, replace it instead',
            \addcslashes($name, '"'),
        );

        return new self($message);
    }
}
