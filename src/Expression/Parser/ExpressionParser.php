<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Parser;

use FFI\Preprocessor\Expression\Node\ExpressionInterface;
use FFI\Preprocessor\Expression\Parser\Exception\SyntaxErrorException;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Parser\Exception\ParserRuntimeException;

/**
 * Parses the controlling expression of a conditional directive whose
 * identifiers have been replaced (6.10.2p13): integer and character
 * constants, the unary, binary and conditional operators, the comma and the
 * parentheses.
 *
 * @template-extends CompiledExpressionParser<ExpressionInterface>
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.6 Constant expressions
 */
final class ExpressionParser extends CompiledExpressionParser implements
    ExpressionParserInterface
{
    /**
     * @throws SyntaxErrorException in case of the text is not a valid expression
     */
    public function parse(ReadableInterface $source): ExpressionInterface
    {
        try {
            $expression = parent::parse($source);
        } catch (ParserRuntimeException $e) {
            throw SyntaxErrorException::becauseUnexpectedToken($e->token, $source);
        }

        return $expression;
    }
}
