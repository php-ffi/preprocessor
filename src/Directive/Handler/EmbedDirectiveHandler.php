<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\GeneratedChunk;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;

/**
 * `#embed` includes a binary resource (6.10.4).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.4 Binary resource inclusion
 */
final class EmbedDirectiveHandler extends DirectiveHandler
{
    /**
     * @throws InvalidDirectiveBodyException in case of the body does not start with a resource name
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

        $content = $file->content;

        if ($content === '') {
            return [];
        }

        $text = \implode(',', \array_map(\ord(...), \str_split($content)));

        return [new GeneratedChunk($text, $directive->source, $directive->getPhysicalOffset(0))];
    }
}
