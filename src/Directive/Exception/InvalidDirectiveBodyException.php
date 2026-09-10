<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\RuntimeException;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Occurs when the body of a directive is not what the directive takes.
 */
final class InvalidDirectiveBodyException extends RuntimeException
{
    /**
     * @param string $directive name of the directive
     * @param int<0, max> $offset offset of the first byte of the body in the source
     */
    public static function becauseMacroNameIsInvalid(ReadableInterface $source, int $offset, string $directive): self
    {
        return new self(\sprintf('#%s expects an identifier as a macro name', $directive), $source, $offset);
    }

    /**
     * @param string $directive name of the directive
     * @param int<0, max> $offset offset of the first byte of the body in the source
     */
    public static function becauseHeaderNameIsInvalid(ReadableInterface $source, int $offset, string $directive): self
    {
        return new self(\sprintf('#%s expects "FILENAME" or <FILENAME>', $directive), $source, $offset);
    }
}
