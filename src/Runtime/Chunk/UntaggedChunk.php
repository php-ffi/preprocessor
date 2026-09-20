<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Chunk;

use FFI\Preprocessor\Runtime\Chunk;
use FFI\Preprocessor\Runtime\Chunk\SourceMap\Edit;
use FFI\Preprocessor\Runtime\Chunk\SourceMap\SourceMap;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Chunk of a file that the reader has cleaned by the translation phases 1 to 3
 * and nothing is known of its meaning yet: it may hold source text, directives
 * or both, and may end in the middle of any of them.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 */
final class UntaggedChunk extends Chunk
{
    /**
     * Negative offset of a search that leaves the last byte of the text out.
     */
    private const BEFORE_LAST_BYTE = -2;

    /**
     * Creates a chunk of a text that skips nothing of its file.
     *
     * @param int<0, max> $offset offset in the file of the first byte of the text
     */
    public static function createFromString(FileInterface $source, string $content, int $offset = 0): self
    {
        return new self($content, $source, $offset, SourceMap::createEmpty());
    }

    public function isEmpty(): bool
    {
        return $this->content === '';
    }

    /**
     * Returns the chunk of the text from the given offset on, or of the given
     * length, at its place in the file.
     *
     * @param int $offset offset in the text
     * @param int|null $length the rest of the text in case of {@see null}
     */
    public function slice(int $offset, ?int $length = null): self
    {
        $length ??= \strlen($this->content) - $offset;

        \assert($offset >= 0 && $length >= 0);

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

    /**
     * Returns the chunk followed by the one that continues it in the file.
     */
    public function concat(self $next): self
    {
        if ($this->content === '') {
            return $next;
        }

        if ($next->content === '') {
            return $this;
        }

        $length = \strlen($this->content);
        $gap = $next->offset - $this->getPhysicalOffset($length);

        \assert($gap >= 0);

        return new self(
            content: $this->content . $next->content,
            source: $this->source,
            offset: $this->offset,
            map: $this->map->concat($next->map, $length, $gap),
        );
    }

    /**
     * Returns the chunk after the edits are applied to its text.
     *
     * @param list<Edit> $edits in ascending order of the offsets, not
     *        overlapping
     */
    public function edit(array $edits): self
    {
        if ($edits === []) {
            return $this;
        }

        $content = '';
        $position = 0;

        foreach ($edits as $edit) {
            $content .= \substr($this->content, $position, $edit->offset - $position) . $edit->replacement;
            $position = $edit->offset + $edit->length;
        }

        return new self(
            content: $content . \substr($this->content, $position),
            source: $this->source,
            offset: $this->offset,
            map: $this->map->withEdits($edits),
        );
    }

    /**
     * Returns the offset of the first byte of the line the byte at the given
     * offset belongs to.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    public function getLineStart(int $offset): int
    {
        if ($offset === 0) {
            return 0;
        }

        $before = $offset - \strlen($this->content) - 1;
        $lineFeed = \strrpos($this->content, "\n", $before);
        $carriageReturn = \strrpos($this->content, "\r", $before);
        $start = 0;

        if ($lineFeed !== false) {
            $start = $lineFeed + 1;
        }

        if ($carriageReturn !== false && $carriageReturn >= $start) {
            $start = $carriageReturn + 1;
        }

        /** @var int<0, max> $start */
        return $start;
    }

    /**
     * Returns the offset right after the last new-line of the text, {@see null}
     * in case of there is none. A carriage return the text ends with is not
     * counted: the line feed of its "\r\n" may follow.
     *
     * @return int<1, max>|null
     */
    public function findLastLineEnd(): ?int
    {
        $length = \strlen($this->content);

        if ($length === 0) {
            return null;
        }

        $lineFeed = \strrpos($this->content, "\n");
        $carriageReturn = false;

        if ($this->content[$length - 1] !== "\r") {
            $carriageReturn = \strrpos($this->content, "\r");
        } elseif ($length > 1) {
            $carriageReturn = \strrpos($this->content, "\r", self::BEFORE_LAST_BYTE);
        }

        $end = null;

        if ($lineFeed !== false) {
            $end = $lineFeed;
        }

        if ($carriageReturn !== false && ($end === null || $carriageReturn > $end)) {
            $end = $carriageReturn;
        }

        if ($end === null) {
            return null;
        }

        /** @var int<1, max> */
        return $end + 1;
    }
}
