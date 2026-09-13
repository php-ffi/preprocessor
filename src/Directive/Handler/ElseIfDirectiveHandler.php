<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\OutputIndependentDirectiveHandlerInterface;
use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Expression\Evaluator\Exception\EvaluationException;
use FFI\Preprocessor\Expression\Parser\Exception\SyntaxErrorException;
use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#elif` starts the next branch of a conditional section by the value of an expression (6.10.2).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 */
final class ElseIfDirectiveHandler extends DirectiveHandler implements OutputIndependentDirectiveHandlerInterface
{
    /**
     * @throws MisplacedDirectiveException in case of there is no open section or it has the `#else` already
     * @throws InvalidDirectiveBodyException in case of the `defined` operator has no identifier
     * @throws MacroInvocationException in case of an invocation of a macro in the expression is malformed
     * @throws SyntaxErrorException in case of the expression is not valid
     * @throws EvaluationException in case of the expression has no valid value
     * @throws InternalException in case of the expression has a node of an unknown kind
     */
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        \assert($directive->name !== '');

        $output = $context->output;

        if (!$output->hasSection()) {
            throw MisplacedDirectiveException::becauseOpeningIsMissing(
                $directive->source,
                $directive->getPhysicalOffset(0),
                $directive->name,
                'if',
            );
        }

        if ($output->hasLastBranch()) {
            throw MisplacedDirectiveException::becauseEndingIsPassed(
                $directive->source,
                $directive->getPhysicalOffset(0),
                $directive->name,
                'else',
            );
        }

        if ($output->hasBranchTaken()) {
            $output->swap(false);

            return [];
        }

        $output->swap($this->evaluateCondition($context, $directive));

        return [];
    }
}
