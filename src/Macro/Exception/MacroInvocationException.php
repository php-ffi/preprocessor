<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Macro\Exception;

/**
 * Occurs when an invocation of a function-like macro is malformed: its
 * argument list is not closed or has not as many arguments as the macro takes
 * (6.10.5.1p4).
 */
final class MacroInvocationException extends MacroException
{
    public static function becauseArgumentListIsUnterminated(string $name): self
    {
        $message = \sprintf('Argument list of macro "%s" is not closed', \addcslashes($name, '"'));

        return new self($message);
    }

    /**
     * @param int<0, max> $expected the number of the arguments the macro takes
     * @param int<0, max> $given the number of the arguments of the invocation
     */
    public static function becauseArgumentCountIsWrong(string $name, int $expected, int $given): self
    {
        $message = \sprintf('Macro "%s" takes %d arguments, %d given', \addcslashes($name, '"'), $expected, $given);

        return new self($message);
    }

    /**
     * @param int<0, max> $expected the least number of the arguments the macro takes
     * @param int<0, max> $given the number of the arguments of the invocation
     */
    public static function becauseArgumentsAreTooFew(string $name, int $expected, int $given): self
    {
        $message = \sprintf(
            'Macro "%s" takes at least %d arguments, %d given',
            \addcslashes($name, '"'),
            $expected,
            $given,
        );

        return new self($message);
    }
}
