<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Chunk\TaggedChunk;

use FFI\Preprocessor\Stream\Chunk\SourceMap\SourceMap;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Line of a preprocessing directive, from `#` to the new-line that ends it,
 * the new-line excluded, such as `#define X 1` or `#  include <a.h>`.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 */
final class SourceDirective extends TaggedChunk
{
    /**
     * The rest of the line after the name and the blanks after it, such as
     * `X 1` in `#define X 1`
     */
    public readonly string $body;

    /**
     * @param int<0, max> $offset
     */
    public function __construct(
        /**
         * The name of the directive, such as `define`. Empty for the null
         * directive (a lone `#`) and for a line whose `#` is not followed by
         * an identifier, such as `# 42 "file.h"`
         */
        public readonly string $name,
        /**
         * Offset in {@see $content} of the first byte of the body
         *
         * @var int<0, max>
         */
        public readonly int $bodyOffset,
        string $content,
        FileInterface $source,
        int $offset,
        SourceMap $map,
    ) {
        $this->body = \substr($content, $bodyOffset);

        parent::__construct($content, $source, $offset, $map);
    }
}
