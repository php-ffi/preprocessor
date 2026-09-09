<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\OutputIndependentDirectiveHandlerInterface;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#else` starts the last branch of a conditional section (6.10.2).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 */
final class ElseDirectiveHandler implements OutputIndependentDirectiveHandlerInterface
{
    /**
     * @throws MisplacedDirectiveException in case of there is no open section or it has the `#else` already
     */
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        $output = $context->output;

        if (!$output->hasSection()) {
            throw MisplacedDirectiveException::becauseOpeningIsMissing(
                $directive->source,
                $directive->getPhysicalOffset(0),
                'else',
                'if',
            );
        }

        if ($output->hasLastBranch()) {
            throw MisplacedDirectiveException::becauseEndingIsPassed(
                $directive->source,
                $directive->getPhysicalOffset(0),
                'else',
                'else',
            );
        }

        $context->reportExtraTokens($directive);
        $output->swapLast();

        return [];
    }
}
