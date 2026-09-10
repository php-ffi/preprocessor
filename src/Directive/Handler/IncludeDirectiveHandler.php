<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\GeneratedChunk;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#include` includes a header or a source file (6.10.3).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.3 Source file inclusion
 */
final class IncludeDirectiveHandler extends DirectiveHandler
{
    /**
     * @throws InvalidDirectiveBodyException in case of the body does not start with a header name
     */
    public function handle(SourceDirective $directive, DirectiveContext $context): iterable
    {
        $name = $this->getHeaderName($directive);
        $this->reportExtraTokens($context, $directive, $name->length);

        $file = $this->findFile($context, $directive, $name);

        if ($file === null) {
            $this->reportMissingFile($context, $directive, $name);

            return [];
        }

        // TODO: the included file is executed by the stream executor, until it exists the place is marked
        $text = \sprintf("/* the file \"%s\" must be included from the stream executor */\n", $file->pathname);

        return [new GeneratedChunk($text, $directive->source, $directive->getPhysicalOffset(0))];
    }
}
