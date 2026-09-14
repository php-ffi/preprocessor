<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Directive\Exception;

use FFI\Preprocessor\Exception\RuntimeException;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;

/**
 * Occurs when the included files are nested deeper than the limit, such as
 * a file that includes itself (6.10.3p7).
 */
final class IncludeDepthException extends RuntimeException
{
    /**
     * @param int<1, max> $limit the number of files that may be open at once
     */
    public static function becauseLimitIsExceeded(DirectiveChunk $directive, int $limit): self
    {
        return new self(
            \sprintf('#%s nests the files deeper than the limit of %d', $directive->name, $limit),
            $directive->source,
            $directive->getPhysicalOffset(0),
            $directive->getPhysicalLength(0, \max(1, \strlen($directive->content))),
        );
    }
}
