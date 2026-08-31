<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Expression\Evaluator\Exception\EvaluationException;
use FFI\Preprocessor\Expression\Evaluator\ExpressionEvaluator\CharConverter;
use FFI\Preprocessor\Expression\Evaluator\ExpressionEvaluator\Operand;
use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverFactory;
use FFI\Preprocessor\Expression\Evaluator\Math\MathDriverInterface;
use FFI\Preprocessor\Expression\Node\BinaryExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\AddExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\BitwiseAndExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\BitwiseOrExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\BitwiseXorExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\CommaExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\DivExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\EqualExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\GreaterExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\GreaterOrEqualExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\LessExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\LessOrEqualExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\LogicalAndExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\LogicalOrExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\MulExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\NotEqualExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\RemExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\ShiftLeftExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\ShiftRightExpression;
use FFI\Preprocessor\Expression\Node\BinaryExpression\SubExpression;
use FFI\Preprocessor\Expression\Node\ConditionalExpression;
use FFI\Preprocessor\Expression\Node\ExpressionInterface;
use FFI\Preprocessor\Expression\Node\Literal\CharLiteral;
use FFI\Preprocessor\Expression\Node\Literal\IntLiteral;
use FFI\Preprocessor\Expression\Node\UnaryExpression;
use FFI\Preprocessor\Expression\Node\UnaryExpression\BitwiseNotExpression;
use FFI\Preprocessor\Expression\Node\UnaryExpression\LogicalNotExpression;
use FFI\Preprocessor\Expression\Node\UnaryExpression\MinusExpression;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * Evaluates the controlling expression of a conditional directive (6.10.2):
 * every integer is `intmax_t` or `uintmax_t`, an operand of an unsigned type
 * makes the operation unsigned. Only the evaluated part of the expression
 * may fail (6.6p3): `0 && 1 / 0` is `0`, the operand that is not evaluated
 * still gives its type, `0 ? 1u : -1` is unsigned.
 *
 * @template TNumber of mixed
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.6 Constant expressions
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class ExpressionEvaluator implements ExpressionEvaluatorInterface
{
    /**
     * The width of `intmax_t` and `uintmax_t` in bits
     */
    private const WIDTH = 64;

    /**
     * The greatest value of `uintmax_t` in each base, in lowercase
     */
    private const MAX_DIGITS = [
        2 => '1111111111111111111111111111111111111111111111111111111111111111',
        8 => '1777777777777777777777',
        10 => '18446744073709551615',
        16 => 'ffffffffffffffff',
    ];

    /**
     * The least signed value, -2^63
     *
     * @var TNumber
     */
    private readonly mixed $min;

    /**
     * @var TNumber
     */
    private readonly mixed $zero;

    /**
     * @var TNumber
     */
    private readonly mixed $one;

    /**
     * @var TNumber
     */
    private readonly mixed $minusOne;

    /**
     * The width of the integers, the least number of bits a shift cannot
     * shift by
     *
     * @var TNumber
     */
    private readonly mixed $width;

    /**
     * @var MathDriverInterface<TNumber>
     */
    private readonly MathDriverInterface $math;

    /**
     * @param MathDriverInterface<TNumber>|null $math
     */
    public function __construct(
        ?MathDriverInterface $math = null,
    ) {
        $this->math = $this->createMathDriver($math);

        $this->min = $this->math->createFromDigits('8000000000000000', 16);
        $this->zero = $this->math->createFromInt(0);
        $this->one = $this->math->createFromInt(1);
        $this->minusOne = $this->math->createFromInt(-1);
        $this->width = $this->math->createFromInt(self::WIDTH);
    }

    /**
     * @param MathDriverInterface<TNumber>|null $math
     * @return MathDriverInterface<TNumber>
     */
    private function createMathDriver(?MathDriverInterface $math): MathDriverInterface
    {
        if ($math === null) {
            return MathDriverFactory::createDefault();
        }

        return $math;
    }

    public function evaluate(ExpressionInterface $expression, ReadableInterface $source): Value
    {
        $result = $this->evaluateNode($expression, $source, true);

        $value = $this->math->toString($result->number, $result->isUnsigned);

        return new Value($value, $result->isUnsigned);
    }

    /**
     * @param bool $isEvaluated whether the node is in the evaluated part: a
     *        node that is not gives its type and never fails
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the evaluated part has no valid value
     */
    private function evaluateNode(ExpressionInterface $node, ReadableInterface $source, bool $isEvaluated): Operand
    {
        return match (true) {
            $node instanceof IntLiteral => $this->evaluateInt($node, $source),
            $node instanceof CharLiteral => new Operand($this->math->createFromInt(CharConverter::toInt($node, $source)), $node->prefix->isUnsigned()),
            $node instanceof UnaryExpression => $this->evaluateUnary($node, $source, $isEvaluated),
            $node instanceof LogicalAndExpression => $this->evaluateLogical($node, $source, $isEvaluated, false),
            $node instanceof LogicalOrExpression => $this->evaluateLogical($node, $source, $isEvaluated, true),
            $node instanceof CommaExpression => $this->evaluateComma($node, $source, $isEvaluated),
            $node instanceof BinaryExpression => $this->evaluateBinary($node, $source, $isEvaluated),
            $node instanceof ConditionalExpression => $this->evaluateConditional($node, $source, $isEvaluated),
            default => throw EvaluationException::becauseExpressionIsUnknown($node::class, $source, $node->offset),
        };
    }

    /**
     * A constant that does not fit `intmax_t` is unsigned, whatever its
     * suffix, as GCC does with a decimal one. An imaginary constant and a
     * constant beyond `uintmax_t` fail in the part that is not evaluated too.
     *
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the constant is imaginary or beyond `uintmax_t`
     */
    private function evaluateInt(IntLiteral $node, ReadableInterface $source): Operand
    {
        if ($node->isImaginary) {
            throw EvaluationException::becauseConstantIsImaginary($source, $node->offset, \strlen($node->raw));
        }

        if (self::isTooLarge($node)) {
            throw EvaluationException::becauseConstantIsTooLarge($source, $node->offset, \strlen($node->raw));
        }

        $number = $this->math->createFromDigits($node->digits, $node::getBase());

        return new Operand($number, $node->suffix?->isUnsigned() === true || $this->math->isNegative($number));
    }

    /**
     * Whether the value of the constant goes beyond `uintmax_t`.
     */
    private static function isTooLarge(IntLiteral $node): bool
    {
        $max = self::MAX_DIGITS[$node::getBase()];

        if (\strlen($node->digits) !== \strlen($max)) {
            return \strlen($node->digits) > \strlen($max);
        }

        return \strcmp($node->digits, $max) > 0;
    }

    /**
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the evaluated part has no valid value
     */
    private function evaluateUnary(UnaryExpression $node, ReadableInterface $source, bool $isEvaluated): Operand
    {
        $operand = $this->evaluateNode($node->operand, $source, $isEvaluated);

        if ($node instanceof LogicalNotExpression) {
            return $this->createBool($this->math->isZero($operand->number));
        }

        if ($node instanceof BitwiseNotExpression) {
            return new Operand($this->math->bitwiseNot($operand->number), $operand->isUnsigned);
        }

        if (!$node instanceof MinusExpression) {
            return $operand;
        }

        if ($isEvaluated && !$operand->isUnsigned && $this->isMin($operand->number)) {
            throw EvaluationException::becauseIntegerOverflows($source, $node->offset);
        }

        return new Operand($this->math->negate($operand->number), $operand->isUnsigned);
    }

    /**
     * Evaluates `&&` and `||`: the right operand is evaluated only in case of
     * the left one does not decide the result.
     *
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the evaluated part has no valid value
     */
    private function evaluateLogical(BinaryExpression $node, ReadableInterface $source, bool $isEvaluated, bool $isOr): Operand
    {
        $left = !$this->math->isZero($this->evaluateNode($node->left, $source, $isEvaluated)->number);
        $right = !$this->math->isZero($this->evaluateNode($node->right, $source, $isEvaluated && $left !== $isOr)->number);

        return $this->createBool($isOr ? $left || $right : $left && $right);
    }

    /**
     * The comma operator is allowed only in the part that is not evaluated
     * (6.6p3).
     *
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the comma is evaluated
     */
    private function evaluateComma(CommaExpression $node, ReadableInterface $source, bool $isEvaluated): Operand
    {
        if ($isEvaluated) {
            throw EvaluationException::becauseCommaIsEvaluated($source, $node->offset);
        }

        $this->evaluateNode($node->left, $source, false);

        return $this->evaluateNode($node->right, $source, false);
    }

    /**
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the evaluated part has no valid value
     */
    private function evaluateConditional(ConditionalExpression $node, ReadableInterface $source, bool $isEvaluated): Operand
    {
        $condition = !$this->math->isZero($this->evaluateNode($node->condition, $source, $isEvaluated)->number);
        $then = $this->evaluateNode($node->then, $source, $isEvaluated && $condition);
        $else = $this->evaluateNode($node->else, $source, $isEvaluated && !$condition);

        // The result has the common type of the both operands (6.5.16p5)
        return new Operand($condition ? $then->number : $else->number, $then->isUnsigned || $else->isUnsigned);
    }

    /**
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the evaluated part has no valid value
     */
    private function evaluateBinary(BinaryExpression $node, ReadableInterface $source, bool $isEvaluated): Operand
    {
        $left = $this->evaluateNode($node->left, $source, $isEvaluated);
        $right = $this->evaluateNode($node->right, $source, $isEvaluated);

        if ($node instanceof ShiftLeftExpression || $node instanceof ShiftRightExpression) {
            return $this->evaluateShift($node, $source, $left, $right, $isEvaluated);
        }

        // The usual arithmetic conversions: an unsigned operand makes both unsigned
        $isUnsigned = $left->isUnsigned || $right->isUnsigned;
        $a = $left->number;
        $b = $right->number;
        $math = $this->math;

        return match (true) {
            $node instanceof LessExpression => $this->createBool($math->compare($a, $b, $isUnsigned) < 0),
            $node instanceof GreaterExpression => $this->createBool($math->compare($a, $b, $isUnsigned) > 0),
            $node instanceof LessOrEqualExpression => $this->createBool($math->compare($a, $b, $isUnsigned) <= 0),
            $node instanceof GreaterOrEqualExpression => $this->createBool($math->compare($a, $b, $isUnsigned) >= 0),
            $node instanceof EqualExpression => $this->createBool($math->compare($a, $b, $isUnsigned) === 0),
            $node instanceof NotEqualExpression => $this->createBool($math->compare($a, $b, $isUnsigned) !== 0),
            $node instanceof BitwiseAndExpression => new Operand($math->bitwiseAnd($a, $b), $isUnsigned),
            $node instanceof BitwiseOrExpression => new Operand($math->bitwiseOr($a, $b), $isUnsigned),
            $node instanceof BitwiseXorExpression => new Operand($math->bitwiseXor($a, $b), $isUnsigned),
            default => $this->evaluateArithmetic($node, $source, $a, $b, $isUnsigned, $isEvaluated),
        };
    }

    /**
     * Evaluates `*`, `/`, `%`, `+` and `-`, a signed result must fit
     * `intmax_t`.
     *
     * @param TNumber $a
     * @param TNumber $b
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the evaluated operation has no valid value
     * @throws InternalException in case of the expression has a node of an unknown kind
     */
    private function evaluateArithmetic(
        BinaryExpression $node,
        ReadableInterface $source,
        mixed $a,
        mixed $b,
        bool $isUnsigned,
        bool $isEvaluated,
    ): Operand {
        $math = $this->math;

        if ($node instanceof DivExpression || $node instanceof RemExpression) {
            $isInvalid = $math->isZero($b)
                || (!$isUnsigned && $this->isMin($a) && $this->isMinusOne($b));

            if ($isInvalid) {
                if (!$isEvaluated) {
                    return new Operand($this->zero, $isUnsigned);
                }

                $length = $node->right->offset - $node->left->offset;

                if ($math->isZero($b)) {
                    throw EvaluationException::becauseDivisionByZero($source, $node->offset, $length);
                }

                throw EvaluationException::becauseIntegerOverflows($source, $node->offset, $length);
            }

            $result = $node instanceof DivExpression
                ? $math->divide($a, $b, $isUnsigned)
                : $math->remainder($a, $b, $isUnsigned);

            return new Operand($result, $isUnsigned);
        }

        [$result, $isOverflow] = match (true) {
            $node instanceof MulExpression => [$math->multiply($a, $b), !$isUnsigned && $this->isProductOverflow($a, $b)],
            $node instanceof AddExpression => self::checkSum($math, $a, $b, $math->add($a, $b), $isUnsigned, true),
            $node instanceof SubExpression => self::checkSum($math, $a, $b, $math->subtract($a, $b), $isUnsigned, false),
            default => throw EvaluationException::becauseExpressionIsUnknown($node::class, $source, $node->offset),
        };

        if ($isOverflow && $isEvaluated) {
            throw EvaluationException::becauseIntegerOverflows($source, $node->offset);
        }

        return new Operand($result, $isUnsigned);
    }

    /**
     * Evaluates `<<` and `>>`: the result has the type of the left operand
     * and must fit in it (6.5.8, 6.6p4). The shifts the standard leaves
     * undefined have the values of GCC: a negative count shifts the other
     * way, a count of the width or more gives `0`, or `-1` of a negative
     * operand shifted right, a negative operand is shifted in two's
     * complement.
     *
     * @param Operand<TNumber> $left
     * @param Operand<TNumber> $right
     * @return Operand<TNumber>
     * @throws EvaluationException in case of the evaluated result does not fit
     */
    private function evaluateShift(
        ShiftLeftExpression|ShiftRightExpression $node,
        ReadableInterface $source,
        Operand $left,
        Operand $right,
        bool $isEvaluated,
    ): Operand {
        $math = $this->math;
        $isCountNegative = !$right->isUnsigned && $math->isNegative($right->number);
        $count = $isCountNegative ? $math->negate($right->number) : $right->number;
        $isLeft = ($node instanceof ShiftLeftExpression) !== $isCountNegative;
        $isSigned = !$left->isUnsigned;

        if ($math->compare($count, $this->width, true) >= 0) {
            if ($isLeft) {
                if ($isSigned && !$math->isZero($left->number) && $isEvaluated) {
                    throw EvaluationException::becauseIntegerOverflows($source, $node->offset);
                }

                return new Operand($this->zero, $left->isUnsigned);
            }

            $isFilled = $isSigned && $math->isNegative($left->number);

            return new Operand($isFilled ? $this->minusOne : $this->zero, $left->isUnsigned);
        }

        $bits = (int) $math->toString($count, true);
        \assert($bits >= 0 && $bits < self::WIDTH);

        if (!$isLeft) {
            return new Operand($math->shiftRight($left->number, $bits, $left->isUnsigned), $left->isUnsigned);
        }

        $result = $math->shiftLeft($left->number, $bits);
        $isOverflow = $isSigned && $math->compare($math->shiftRight($result, $bits, false), $left->number, false) !== 0;

        if ($isOverflow && $isEvaluated) {
            throw EvaluationException::becauseIntegerOverflows($source, $node->offset);
        }

        return new Operand($result, $left->isUnsigned);
    }

    /**
     * Tells the overflow of a signed sum or difference: the operands of the
     * same sign for a sum (of different signs for a difference) give a
     * result of the other sign.
     *
     * @template TValue of mixed
     * @param MathDriverInterface<TValue> $math
     * @param TValue $a
     * @param TValue $b
     * @param TValue $result
     * @return array{TValue, bool} the result and whether it overflows
     */
    private static function checkSum(
        MathDriverInterface $math,
        mixed $a,
        mixed $b,
        mixed $result,
        bool $isUnsigned,
        bool $isSum,
    ): array {
        if ($isUnsigned) {
            return [$result, false];
        }

        $isSameSign = $math->isNegative($a) === $math->isNegative($b);

        return [$result, $isSameSign === $isSum && $math->isNegative($result) !== $math->isNegative($a)];
    }

    /**
     * Whether the signed product does not fit `intmax_t`.
     *
     * @param TNumber $a
     * @param TNumber $b
     */
    private function isProductOverflow(mixed $a, mixed $b): bool
    {
        $math = $this->math;

        if ($math->isZero($a) || $math->isZero($b)) {
            return false;
        }

        if ($this->isMinusOne($a)) {
            return $this->isMin($b);
        }

        if ($this->isMinusOne($b)) {
            return $this->isMin($a);
        }

        return $math->compare($math->divide($math->multiply($a, $b), $a, false), $b, false) !== 0;
    }

    /**
     * @param TNumber $number
     */
    private function isMin(mixed $number): bool
    {
        return $this->math->compare($number, $this->min, true) === 0;
    }

    /**
     * @param TNumber $number
     */
    private function isMinusOne(mixed $number): bool
    {
        return $this->math->compare($number, $this->minusOne, true) === 0;
    }

    /**
     * Creates the signed `1` or `0` of a comparison or a logical operator.
     *
     * @return Operand<TNumber>
     */
    private function createBool(bool $value): Operand
    {
        return new Operand($value ? $this->one : $this->zero, false);
    }
}
