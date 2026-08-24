<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

use FFI\Preprocessor\Stream\ChunkInterface;
use Phplrt\Contracts\Lexer\TokenInterface;

/**
 * Occurs when a fragment of the source does not have the form the standard
 * requires there, whether it is a directive, a macro definition or the
 * expression of a conditional directive.
 */
final class SyntaxErrorException extends SourceException
{
    /**
     * @param TokenInterface $token the token read from the text of the chunk
     */
    public static function becauseUnexpectedToken(TokenInterface $token, ChunkInterface $chunk): self
    {
        $message = \sprintf('Syntax error, unexpected "%s"', \addcslashes($token->value, '"'));

        return new self(
            message: $message,
            source: $chunk->source,
            offset: $chunk->getPhysicalOffset($token->offset),
            length: $token->size > 0 ? $chunk->getPhysicalLength($token->offset, $token->size) : 0,
        );
    }
}
