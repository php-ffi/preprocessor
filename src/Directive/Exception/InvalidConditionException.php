<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\RuntimeException;
use FFI\Preprocessor\Expression\Exception\ExpressionException;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Occurs when the controlling expression of `#if` or `#elif` is not valid or has
 * no valid value (6.10.2). It points to the expression as it is written in the
 * directive and prints the failure of the expression text after the replacement
 * of the macros below it.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 */
final class InvalidConditionException extends RuntimeException
{
    private const BLANKS = " \t\f\x0B";

    /**
     * @param int<0, max> $offset
     * @param int<0, max> $length
     */
    private function __construct(
        string $message,
        ReadableInterface $source,
        int $offset,
        int $length,
        /**
         * The failure in the text of the expression after the replacement of the macros
         */
        public readonly ExpressionException $expression,
    ) {
        parent::__construct($message, $source, $offset, $length);
    }

    public static function becauseExpressionIsInvalid(DirectiveChunk $directive, ExpressionException $expression): self
    {
        $length = \max(1, \strlen(\rtrim($directive->body, self::BLANKS)));

        return new self(
            \sprintf('Invalid controlling expression of #%s: %s', \addcslashes($directive->name, '"'), $expression->getMessage()),
            $directive->source,
            $directive->getPhysicalOffset($directive->bodyOffset),
            $directive->getPhysicalLength($directive->bodyOffset, $length),
            $expression,
        );
    }

    public function __toString(): string
    {
        return parent::__toString() . "\n" . $this->expression;
    }
}
