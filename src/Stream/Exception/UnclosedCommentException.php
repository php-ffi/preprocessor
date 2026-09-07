<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Exception;

use FFI\Preprocessor\Exception\ParsingException;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Occurs when a file ends in a block comment that is not closed (6.4.9).
 */
final class UnclosedCommentException extends ParsingException
{
    /**
     * @param int<0, max> $offset offset of the first byte of the comment in the source
     */
    public static function becauseFileEnds(ReadableInterface $source, int $offset): self
    {
        return new self('Unterminated comment', $source, $offset);
    }
}
