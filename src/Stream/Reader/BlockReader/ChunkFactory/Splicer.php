<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader\ChunkFactory;

use FFI\Preprocessor\Stream\Chunk\SourceMap;

/**
 * Translation phase 2 for a fragment of a file: every backslash immediately
 * followed by a new-line (`\n`, `\r\n` or a lone `\r`) is deleted, splicing
 * the physical source lines into the logical ones.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class Splicer
{
    /**
     * Returns the map of the splices of the fragment, empty in case of there
     * are none.
     */
    public function createSourceMap(string $fragment): SourceMap
    {
        if (!\str_contains($fragment, '\\')) {
            return SourceMap::createEmpty();
        }

        $offsets = [];
        $lengths = [];

        foreach ($this->findSplices($fragment) as $offset => $length) {
            $offsets[] = $offset;
            $lengths[] = $length;
        }

        return $offsets === [] ? SourceMap::createEmpty() : SourceMap::createFromSplices($offsets, $lengths);
    }

    /**
     * Returns the fragment without its splices.
     */
    public function removeSplices(string $fragment): string
    {
        $result = '';
        // Start of the text that follows the last splice
        $start = 0;

        foreach ($this->findSplices($fragment) as $offset => $length) {
            $result .= \substr($fragment, $start, $offset - $start);
            $start = $offset + $length;
        }

        return $start === 0 ? $fragment : $result . \substr($fragment, $start);
    }

    /**
     * Yields the offset and the size of each splice of the fragment, in
     * ascending order of the offsets.
     *
     * @return \Generator<int<0, max>, 2|3>
     */
    private function findSplices(string $fragment): \Generator
    {
        $offset = 0;

        while (($offset = \strpos($fragment, '\\', $offset)) !== false) {
            $length = match ($fragment[$offset + 1] ?? '') {
                "\n" => 2,
                "\r" => ($fragment[$offset + 2] ?? '') === "\n" ? 3 : 2,
                default => null,
            };

            if ($length === null) {
                ++$offset;

                continue;
            }

            yield $offset => $length;

            $offset += $length;
        }
    }
}
