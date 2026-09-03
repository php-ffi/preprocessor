<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

use FFI\Preprocessor\Stream\Chunk\SourceMap;

/**
 * Translation phase 2 for a fragment of a file: every backslash followed by
 * a new-line (`\n`, `\r\n` or a lone `\r`) is deleted, splicing the physical
 * source lines into the logical ones. The blanks between the backslash and
 * the new-line are deleted with them, as GCC and Clang do: phase 1 deletes
 * the trailing white space of a line.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class Splicer
{
    /**
     * A line splice
     */
    private const SPLICE_PATTERN = '/\\\\[\x20\t\f\x0B]*+(?:\r\n|\n|\r)/S';

    /**
     * The blanks allowed between the backslash and the new-line of a splice
     */
    private const BLANKS = " \t\f\v";

    /**
     * Returns the map of the splices of the fragment and of the comments it
     * has had replaced by a space, empty in case of there are none.
     *
     * @param list<int<1, max>> $commentOffsets offset in the fragment of the
     *        byte after the space that replaces a comment, in ascending order
     * @param list<int<1, max>> $commentSizes number of the bytes of the file
     *        the space replaces besides itself, of the same index as its
     *        offset
     */
    public function createSourceMap(string $fragment, array $commentOffsets = [], array $commentSizes = []): SourceMap
    {
        $offsets = [];
        $sizes = [];
        $removed = 0;
        $comment = 0;
        $comments = \count($commentOffsets);

        foreach (self::findSplices($fragment) as $offset => $length) {
            for (; $comment < $comments && $commentOffsets[$comment] <= $offset; ++$comment) {
                $offsets[] = $commentOffsets[$comment] - $removed;
                $sizes[] = $commentSizes[$comment];
            }

            $offsets[] = $offset - $removed;
            $sizes[] = $length;
            $removed += $length;
        }

        for (; $comment < $comments; ++$comment) {
            $offsets[] = $commentOffsets[$comment] - $removed;
            $sizes[] = $commentSizes[$comment];
        }

        // @phpstan-ignore-next-line the offsets of the shifts are past the bytes the splices before them have removed
        return $offsets === [] ? SourceMap::createEmpty() : SourceMap::createFromShifts($offsets, $sizes);
    }

    /**
     * Returns the fragment without its splices.
     */
    public function removeSplices(string $fragment): string
    {
        if (!\str_contains($fragment, '\\')) {
            return $fragment;
        }

        return \preg_replace(self::SPLICE_PATTERN, '', $fragment) ?? $fragment;
    }

    /**
     * Whether the new-line at the given offset of the text ends a line
     * splice.
     *
     * @param int<0, max> $offset the offset of `\n` or `\r`
     */
    public function isSpliceEnd(string $text, int $offset): bool
    {
        return $this->findSpliceStart($text, $offset) !== null;
    }

    /**
     * Returns the offset of the backslash of the line splice the new-line at
     * the given offset of the text ends, {@see null} in case of the new-line
     * ends no splice.
     *
     * @param int<0, max> $offset the offset of `\n` or `\r`
     * @return int<0, max>|null
     */
    public function findSpliceStart(string $text, int $offset): ?int
    {
        if ($text[$offset] === "\n" && $offset > 0 && $text[$offset - 1] === "\r") {
            --$offset;
        }

        while ($offset > 0 && \str_contains(self::BLANKS, $text[$offset - 1])) {
            --$offset;
        }

        return $offset > 0 && $text[$offset - 1] === '\\' ? $offset - 1 : null;
    }

    /**
     * Returns the offset right after the last line feed of the text that does
     * not end a line splice, {@see null} in case of there is none.
     *
     * @return int<1, max>|null
     */
    public function findLastLineEnd(string $text): ?int
    {
        $limit = \strlen($text);

        while ($limit > 0) {
            // A negative offset limits the search to the bytes before the limit
            /** @var int<0, max>|false */
            $lineFeed = \strrpos($text, "\n", $limit - \strlen($text) - 1);

            if ($lineFeed === false) {
                return null;
            }

            if (!$this->isSpliceEnd($text, $lineFeed)) {
                return $lineFeed + 1;
            }

            $limit = $lineFeed;
        }

        return null;
    }

    /**
     * Yields the offset and the size of each splice of the fragment, in
     * ascending order of the offsets.
     *
     * @return iterable<int<0, max>, int<2, max>>
     */
    private static function findSplices(string $fragment): iterable
    {
        if (!\str_contains($fragment, '\\')) {
            return [];
        }

        \preg_match_all(self::SPLICE_PATTERN, $fragment, $matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$splice, $offset]) {
            yield \max(0, $offset) => \max(2, \strlen($splice));
        }
    }
}
