<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Exception;

use FFI\Preprocessor\Exception\PreprocessorException;

/**
 * Occurs when a pattern fails to match a text for a reason other than the
 * text itself, such as a limit of the regular expression engine.
 */
final class UnmatchedTextException extends PreprocessorException
{
    /**
     * @param non-empty-string $subject what the pattern has been matched against, such as "line splices"
     */
    public static function becauseTextIsNotMatched(string $subject): self
    {
        $message = \sprintf(
            'The %s cannot be matched: "%s"',
            $subject,
            \addcslashes(\preg_last_error_msg(), '"'),
        );

        return new self($message);
    }
}
