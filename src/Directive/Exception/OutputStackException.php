<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\PreprocessorException;

/**
 * Occurs when a directive handler changes the conditional sections in a way
 * that is impossible, such as closing a section that is not open.
 */
final class OutputStackException extends PreprocessorException
{
    public static function becauseSectionIsMissing(): self
    {
        return new self('The output stack has no open conditional section');
    }

    public static function becauseBranchFollowsLastOne(): self
    {
        return new self('The last branch of the conditional section has been started already');
    }
}
