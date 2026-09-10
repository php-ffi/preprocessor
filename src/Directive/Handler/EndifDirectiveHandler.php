<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\OutputIndependentDirectiveHandlerInterface;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#endif` closes a conditional section (6.10.2).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.2 Conditional inclusion
 */
final class EndifDirectiveHandler extends DirectiveHandler implements OutputIndependentDirectiveHandlerInterface
{
    /**
     * @throws MisplacedDirectiveException in case of there is no open section
     */
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        if (!$context->output->hasSection()) {
            throw MisplacedDirectiveException::becauseOpeningIsMissing(
                $directive->source,
                $directive->getPhysicalOffset(0),
                'endif',
                'if',
            );
        }

        $this->reportExtraTokens($context, $directive);
        $context->output->pop();

        return [];
    }
}
