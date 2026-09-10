<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\GeneratedChunk;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#pragma` passes an implementation-defined instruction (6.10.8).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.8 Pragma directive
 */
final class PragmaDirectiveHandler implements DirectiveHandlerInterface
{
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        $body = \trim($directive->body);

        if ($body === '') {
            return [new GeneratedChunk("#pragma\n", $directive->source, $directive->getPhysicalOffset(0))];
        }

        return [new GeneratedChunk("#pragma $body\n", $directive->source, $directive->getPhysicalOffset(0))];
    }
}
