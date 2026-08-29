<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Expression\Evaluator;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Expression\Evaluator\Exception\EvaluationException;
use FFI\Preprocessor\Expression\Node\ExpressionInterface;
use Phplrt\Contracts\Source\ReadableInterface;

interface ExpressionEvaluatorInterface
{
    /**
     * @param ReadableInterface $source the text the expression is parsed
     *        from, the place of a failure
     * @throws EvaluationException in case of the evaluated part has no valid value
     * @throws InternalException in case of the expression has a node of an unknown kind
     */
    public function evaluate(ExpressionInterface $expression, ReadableInterface $source): Value;
}
