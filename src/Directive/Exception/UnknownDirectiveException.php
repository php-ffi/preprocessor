<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\RuntimeException;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;

/**
 * Occurs when a directive has no handler, such as a misspelled `#defnie`
 * (6.10.1p3).
 */
final class UnknownDirectiveException extends RuntimeException
{
    public static function becauseHandlerIsMissing(DirectiveChunk $directive): self
    {
        return new self(
            \sprintf('Unknown directive "#%s"', \addcslashes($directive->name, '"')),
            $directive->source,
            $directive->getPhysicalOffset(0),
            $directive->getPhysicalLength(0, \max(1, \strlen($directive->content))),
        );
    }
}
