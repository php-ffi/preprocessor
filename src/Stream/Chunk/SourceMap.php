<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Chunk;

/**
 * Line splices removed from the text of a chunk: translates an offset in
 * the text back to the offset from the start of the chunk in its file.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 */
final class SourceMap
{
    private function __construct(
        /**
         * Offsets in the text where a splice has been removed, in ascending
         * order, repeated for the consecutive splices
         *
         * @var list<int<0, max>>
         */
        private readonly array $offsets,
        /**
         * Total size of the splices removed up to the splice of the same
         * index inclusive
         *
         * @var list<int<2, max>>
         */
        private readonly array $shifts,
    ) {}

    /**
     * Creates the map of a text without splices, where every offset stays as
     * it is.
     */
    public static function createEmpty(): self
    {
        return new self([], []);
    }

    /**
     * @param list<int<0, max>> $offsets offset of each splice from the start
     *        of the chunk in the file, in ascending order
     * @param list<int<2, 3>> $lengths size of each splice, of the same index
     *        as its offset
     */
    public static function createFromSplices(array $offsets, array $lengths): self
    {
        $removed = 0;

        foreach ($offsets as $i => $offset) {
            $offsets[$i] = $offset - $removed;
            $removed += $lengths[$i];
            $lengths[$i] = $removed;
        }

        // @phpstan-ignore-next-line a splice never starts before the bytes the previous ones have removed
        return new self($offsets, $lengths);
    }

    /**
     * Whether no splice has been removed from the text.
     */
    public function isEmpty(): bool
    {
        return $this->offsets === [];
    }

    /**
     * Returns the offset from the start of the chunk in the file of the byte
     * at the given offset of the text. An offset right after a splice points
     * past the splice.
     *
     * @param int<0, max> $offset
     * @return int<0, max>
     */
    public function getPhysicalOffset(int $offset): int
    {
        if ($this->offsets === []) {
            return $offset;
        }

        // The number of splices removed at or before the offset
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

        return $low === 0 ? $offset : $offset + $this->shifts[$low - 1];
    }

    /**
     * Returns the size in the file of the fragment of the text, the splices
     * inside the fragment included and the ones around it excluded.
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
