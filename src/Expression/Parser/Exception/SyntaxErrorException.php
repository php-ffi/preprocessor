<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Parser\Exception;

use FFI\Preprocessor\Expression\Exception\ExpressionException;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Occurs when the text is not a valid expression: a token out of place.
 */
final class SyntaxErrorException extends ExpressionException
{
    /**
     * @param TokenInterface $token the token out of place, an empty one at
     *        the end of the text
     * @param ReadableInterface $source the text of the expression
     */
    public static function becauseUnexpectedToken(TokenInterface $token, ReadableInterface $source): self
    {
        if ($token->value === '') {
            return new self('Syntax error, unexpected end of input', $source, $token->offset);
        }

        $message = \sprintf('Syntax error, unexpected "%s"', \addcslashes($token->value, '"'));

        return new self($message, $source, $token->offset, $token->size);
    }
}
