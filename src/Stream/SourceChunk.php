<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream;

/**
 * Source text between the directives, the new-lines included: one or more
 * text lines or lines of a skipped group, which the reader does not tell
 * apart.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 */
final class SourceChunk extends Chunk
{
    /**
     * Returns the fragment of the text as a chunk of its own, at its place
     * in the file.
     *
     * @param int<0, max> $offset
     * @param int<0, max> $length
     */
    public function slice(int $offset, int $length): self
    {
        if ($offset === 0 && $length === \strlen($this->content)) {
            return $this;
        }

        return new self(
            content: \substr($this->content, $offset, $length),
            source: $this->source,
            offset: $this->getPhysicalOffset($offset),
            map: $this->map->slice($offset, $length),
        );
    }
}
