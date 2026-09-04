<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Chunk\SourceMap;

/**
 * Bytes of a file the text read from it skips, such as the line splices
 * removed from it and the comments replaced by a space: translates an offset
 * in the text back to the offset from the start of the text in its file.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 */
final class SourceMap
{
    private function __construct(
        /**
         * Offsets in the text where the file has bytes the text skips, in
         * ascending order
         *
         * @var list<int<0, max>>
         */
        private readonly array $offsets,
        /**
         * Total size of the skipped bytes up to the offset of the same index
         * inclusive, in ascending order
         *
         * @var list<int<1, max>>
         */
        private readonly array $totals,
    ) {}

    /**
     * Creates the map of a text that skips nothing, where every offset stays
     * as it is.
     */
    public static function createEmpty(): self
    {
        return new self([], []);
    }

    /**
     * Creates a map from the pairs of an offset in the text and the total size
     * of the skipped bytes that applies from it. A pair that adds nothing to
     * the ones before it is left out.
     *
     * @param list<int> $offsets
     * @param list<int> $totals the same length as the offsets
     */
    private static function createFromTotals(array $offsets, array $totals): self
    {
        $keptOffsets = [];
        $keptTotals = [];
        $last = 0;

        foreach ($totals as $i => $total) {
            if ($total <= $last || $offsets[$i] < 0) {
                continue;
            }

            $keptOffsets[] = $offsets[$i];
            $keptTotals[] = $total;
            $last = $total;
        }

        return new self($keptOffsets, $keptTotals);
    }

    /**
     * Whether the text skips no byte of the file.
     */
    public function isEmpty(): bool
    {
        return $this->offsets === [];
    }

    /**
     * Returns the offset from the start of the text in the file of the byte
     * at the given offset of the text. An offset right after the skipped
     * bytes points past them.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    public function getPhysicalOffset(int $offset): int
    {
        $count = $this->countShifts($offset);

        if ($count === 0) {
            return $offset;
        }

        return $offset + $this->totals[$count - 1];
    }

    /**
     * Returns the size in the file of the part of the text, the skipped
     * bytes inside the part included and the ones around it excluded.
     *
     * @param int<0, max> $offset
     * @param int<1, max> $length
     * @return int<1, max>
     */
    public function getPhysicalLength(int $offset, int $length): int
    {
        $start = $this->getPhysicalOffset($offset);
        $end = $this->getPhysicalOffset($offset + $length - 1) + 1;

        /** @var int<1, max> */
        return $end - $start;
    }

    /**
     * Returns the map of a part of the text: the skips inside it, relative
     * to its start. The skipped bytes before the part are not a part of
     * its map.
     *
     * @param int<0, max> $offset
     * @param int<0, max> $length
     */
    public function slice(int $offset, int $length): self
    {
        $first = $this->countShifts($offset);
        $last = $this->countShifts(\max($offset, $offset + $length - 1));

        if ($first === $last) {
            return self::createEmpty();
        }

        $before = 0;

        if ($first > 0) {
            $before = $this->totals[$first - 1];
        }

        $offsets = [];
        $totals = [];

        for ($i = $first; $i < $last; ++$i) {
            $offsets[] = $this->offsets[$i] - $offset;
            $totals[] = $this->totals[$i] - $before;
        }

        return self::createFromTotals($offsets, $totals);
    }

    /**
     * Returns the map of the text followed by the text of the other map.
     *
     * @param int<0, max> $length length of the text of this map
     * @param int<0, max> $gap number of the bytes of the file between the end
     *        of this text and the start of the next one
     */
    public function concat(self $next, int $length, int $gap): self
    {
        $offsets = $this->offsets;
        $totals = $this->totals;
        $base = $gap;

        if ($this->totals !== []) {
            $base += $this->totals[\count($this->totals) - 1];
        }

        $offsets[] = $length;
        $totals[] = $base;

        foreach ($next->offsets as $i => $offset) {
            $offsets[] = $offset + $length;
            $totals[] = $base + $next->totals[$i];
        }

        return self::createFromTotals($offsets, $totals);
    }

    /**
     * Returns the map of the text after the edits are applied to it.
     *
     * @param list<Edit> $edits in ascending order of the offsets, not
     *        overlapping
     */
    public function withEdits(array $edits): self
    {
        $offsets = [];
        $totals = [];
        $count = \count($this->offsets);
        $i = 0;
        // Number of the bytes the edits applied so far take away from the text
        $removed = 0;
        // Total size of the skipped bytes at the last passed offset
        $current = 0;

        foreach ($edits as $edit) {
            $start = $edit->offset;
            $end = $start + $edit->length;

            for (; $i < $count && $this->offsets[$i] <= $start; ++$i) {
                $current = $this->totals[$i];
                $offsets[] = $this->offsets[$i] - $removed;
                $totals[] = $current + $removed;
            }

            for (; $i < $count && $this->offsets[$i] <= $end; ++$i) {
                $current = $this->totals[$i];
            }

            $removed += $edit->length - \strlen($edit->replacement);

            $offsets[] = $end - $removed;
            $totals[] = $current + $removed;
        }

        for (; $i < $count; ++$i) {
            $offsets[] = $this->offsets[$i] - $removed;
            $totals[] = $this->totals[$i] + $removed;
        }

        return self::createFromTotals($offsets, $totals);
    }

    /**
     * Returns the number of the places the text skips bytes of the file at
     * or before the given offset.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    private function countShifts(int $offset): int
    {
        $low = 0;
        $high = \count($this->offsets);

        while ($low < $high) {
            $middle = ($low + $high) >> 1;

            if ($this->offsets[$middle] <= $offset) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }
}
