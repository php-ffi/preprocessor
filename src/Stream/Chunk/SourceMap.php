<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Chunk;

/**
 * Bytes of the file the text of a chunk skips, such as the line splices
 * removed from it and the comments replaced by a space: translates an offset
 * in the text back to the offset from the start of the chunk in its file.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 */
final class SourceMap
{
    private function __construct(
        /**
         * Offsets in the text where the file has bytes the text skips, in
         * ascending order, repeated for the consecutive ones
         *
         * @var list<int<0, max>>
         */
        private readonly array $offsets,
        /**
         * Total size of the skipped bytes up to the offset of the same index
         * inclusive
         *
         * @var list<int<1, max>>
         */
        private readonly array $shifts,
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
     * Creates the map of a text the line splices are removed from.
     *
     * @param list<int<0, max>> $offsets offset of each splice from the start
     *        of the chunk in the file, in ascending order
     * @param list<int<2, max>> $lengths size of each splice, of the same index
     *        as its offset
     */
    public static function createFromSplices(array $offsets, array $lengths): self
    {
        $removed = 0;

        foreach ($offsets as $i => $offset) {
            $offsets[$i] = $offset - $removed;
            $removed += $lengths[$i];
        }

        // @phpstan-ignore-next-line a splice never starts before the bytes the previous ones have removed
        return self::createFromShifts($offsets, $lengths);
    }

    /**
     * Creates the map of a text that skips the bytes of the file after the
     * given offsets.
     *
     * @param list<int<0, max>> $offsets offset in the text where the bytes are
     *        skipped, in ascending order
     * @param list<int<1, max>> $sizes number of the bytes skipped, of the same
     *        index as its offset
     */
    public static function createFromShifts(array $offsets, array $sizes): self
    {
        $total = 0;
        $shifts = [];

        foreach ($sizes as $size) {
            $total += $size;
            $shifts[] = $total;
        }

        return new self($offsets, $shifts);
    }

    /**
     * Whether the text skips no byte of the file.
     */
    public function isEmpty(): bool
    {
        return $this->offsets === [];
    }

    /**
     * Returns the offset from the start of the chunk in the file of the byte
     * at the given offset of the text. An offset right after the skipped
     * bytes points past them.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    public function getPhysicalOffset(int $offset): int
    {
        $count = $this->countShifts($offset);

        return $count === 0 ? $offset : $offset + $this->shifts[$count - 1];
    }

    /**
     * Returns the number of the places the text skips bytes of the file at
     * or before the given offset.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    public function countShifts(int $offset): int
    {
        if ($this->offsets === []) {
            return 0;
        }

        // The number of the shifts at or before the offset
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

    /**
     * Returns the map of a fragment of the text: the shifts inside it,
     * relative to its start.
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

        $removed = $first === 0 ? 0 : $this->shifts[$first - 1];
        $offsets = [];
        $shifts = [];

        for ($i = $first; $i < $last; ++$i) {
            $offsets[] = $this->offsets[$i] - $offset;
            $shifts[] = $this->shifts[$i] - $removed;
        }

        // @phpstan-ignore-next-line the shifts inside the fragment are past its start and skip at least one byte each
        return new self($offsets, $shifts);
    }

    /**
     * Returns the size in the file of the fragment of the text, the skipped
     * bytes inside the fragment included and the ones around it excluded.
     *
     * @param int<0, max> $offset
     * @param int<1, max> $length
     * @return int<1, max>
     */
    public function getPhysicalLength(int $offset, int $length): int
    {
        $start = $this->getPhysicalOffset($offset);
        $end = $this->getPhysicalOffset($offset + $length - 1) + 1;

        // @phpstan-ignore-next-line the last byte of a fragment never precedes its first byte
        return $end - $start;
    }
}
