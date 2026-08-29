<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator\Exception;

use FFI\Preprocessor\Expression\Exception\ExpressionException;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Occurs when the expression has no valid value: an imaginary constant, a
 * constant beyond `uintmax_t`, a character constant that does not fit its
 * type, or in its evaluated part a division by zero, a signed overflow, a
 * comma operator.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.6 Constant expressions
 */
final class EvaluationException extends ExpressionException
{
    /**
     * @param class-string $class the class of the node of the expression
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the operation in the text
     * @param int<0, max> $length the size of the expression
     */
    public static function becauseExpressionIsUnknown(string $class, ReadableInterface $source, int $offset, int $length = 0): self
    {
        return new self(\sprintf('The expression %s cannot be evaluated', $class), $source, $offset, $length);
    }

    /**
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the operation in the text
     * @param int<0, max> $length the size of the expression
     */
    public static function becauseDivisionByZero(ReadableInterface $source, int $offset, int $length = 0): self
    {
        return new self('Division by zero in the expression', $source, $offset, $length);
    }

    /**
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the operation in the text
     * @param int<0, max> $length the size of the expression
     */
    public static function becauseIntegerOverflows(ReadableInterface $source, int $offset, int $length = 0): self
    {
        return new self('Integer overflow in the expression', $source, $offset, $length);
    }

    /**
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the constant in the text
     * @param int<0, max> $length the size of the constant
     */
    public static function becauseConstantIsTooLarge(ReadableInterface $source, int $offset, int $length): self
    {
        return new self('Integer constant is too large for its type', $source, $offset, $length);
    }

    /**
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the constant in the text
     * @param int<0, max> $length the size of the constant
     */
    public static function becauseConstantIsImaginary(ReadableInterface $source, int $offset, int $length): self
    {
        return new self('Imaginary constant in the expression', $source, $offset, $length);
    }

    /**
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the constant in the text
     * @param int<0, max> $length the size of the constant
     */
    public static function becauseCharacterIsTooLong(ReadableInterface $source, int $offset, int $length): self
    {
        return new self('Character constant too long for its type', $source, $offset, $length);
    }

    /**
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the constant in the text
     * @param int<0, max> $length the size of the constant
     */
    public static function becauseCharacterIsNotEncodable(ReadableInterface $source, int $offset, int $length): self
    {
        return new self('Character not encodable in a single code unit', $source, $offset, $length);
    }

    /**
     * @param ReadableInterface $source the text of the expression
     * @param int<0, max> $offset the offset of the comma operator in the text
     * @param int<0, max>|null $length the size of the expression
     */
    public static function becauseCommaIsEvaluated(ReadableInterface $source, int $offset, ?int $length = null): self
    {
        return new self('Comma operator in the evaluated part of the expression', $source, $offset);
    }
}
