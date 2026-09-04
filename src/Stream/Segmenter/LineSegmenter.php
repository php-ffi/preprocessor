<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Segmenter;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceChunk;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;

/**
 * Directive is a line whose first token is `#` or `%:`, any other line is
 * source text.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream
 */
final class LineSegmenter implements SegmenterInterface
{
    /**
     * The line of a directive without its new-line, the blanks before the
     * marker are the first group and the marker is the second one.
     */
    private const DIRECTIVE_PATTERN = '~(*ANYCRLF)^([ \t\f\x0B]*+)(#(?!#)|%:(?!%:))[^\r\n]*+~m';

    /**
     * The name of a directive and the blanks around it.
     */
    private const NAME_PATTERN = '~\G[ \t\f\x0B]*+([A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*+)?[ \t\f\x0B]*+~';

    /**
     * Blanks and new-lines.
     */
    private const BLANKS = " \t\r\n\f\x0B";

    /**
     * @return \Generator<int, SourceChunk|SourceDirective>
     * @throws InternalException in case of the text cannot be matched
     */
    public function segment(iterable $chunks): \Generator
    {
        $rest = null;

        foreach ($chunks as $chunk) {
            $pending = $chunk;

            if ($rest !== null) {
                $pending = $rest->concat($chunk);
            }

            $end = $pending->findLastLineEnd();
            $rest = $pending;

            if ($end !== null) {
                yield from $this->split($pending->slice(0, $end));

                $rest = $pending->slice($end);
            }
        }

        if ($rest !== null && !$rest->isEmpty()) {
            yield from $this->split($rest);
        }
    }

    /**
     * Splits the complete lines.
     *
     * @return \Generator<int, SourceChunk|SourceDirective>
     * @throws InternalException in case of the text cannot be matched
     */
    private function split(UntaggedChunk $lines): \Generator
    {
        $position = 0;

        foreach ($this->getDirectives($lines->content) as [[$line, $start], [$blanks], [$marker]]) {
            $text = $this->findSourceChunk($lines->slice($position, $start - $position));

            if ($text !== null) {
                yield $text;
            }

            $directive = $lines->slice($start + \strlen($blanks), \strlen($line) - \strlen($blanks));

            yield $this->createDirective($directive, $marker);

            $position = $start + \strlen($line) + $this->measureNewLine($lines->content, $start + \strlen($line));
        }

        $text = $this->findSourceChunk($lines->slice($position));

        if ($text !== null) {
            yield $text;
        }
    }

    /**
     * Returns the lines of the directives: the line, the blanks before the
     * marker and the marker, each with its offset.
     *
     * @return list<array{array{string, int<0, max>}, array{string, int<0, max>}, array{string, int<0, max>}}>
     * @throws InternalException in case of the text cannot be matched
     */
    private function getDirectives(string $text): array
    {
        if (\preg_match_all(self::DIRECTIVE_PATTERN, $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE) === false) {
            throw InternalException::becauseTextIsNotMatched('lines');
        }

        /** @var list<array{array{string, int<0, max>}, array{string, int<0, max>}, array{string, int<0, max>}}> */
        return $matches;
    }

    /**
     * Returns the size of the new-line at the offset, `0` in case of there is none.
     *
     * @return int<0, 2>
     */
    private function measureNewLine(string $text, int $offset): int
    {
        if (($text[$offset] ?? '') === "\r") {
            if (($text[$offset + 1] ?? '') === "\n") {
                return 2;
            }

            return 1;
        }

        if (($text[$offset] ?? '') === "\n") {
            return 1;
        }

        return 0;
    }

    /**
     * Returns the source text, or {@see null} in case of it consists of the
     * blanks and the new-lines only.
     */
    private function findSourceChunk(UntaggedChunk $chunk): ?SourceChunk
    {
        if (\strspn($chunk->content, self::BLANKS) === \strlen($chunk->content)) {
            return null;
        }

        return new SourceChunk($chunk->content, $chunk->source, $chunk->offset, $chunk->map);
    }

    /**
     * @param string $marker the `#` or `%:` the line starts with
     * @throws InternalException in case of the line cannot be matched
     */
    private function createDirective(UntaggedChunk $line, string $marker): SourceDirective
    {
        if (\preg_match(self::NAME_PATTERN, $line->content, $matches, offset: \strlen($marker)) !== 1) {
            throw InternalException::becauseTextIsNotMatched('directive');
        }

        return new SourceDirective(
            name: $matches[1] ?? '',
            bodyOffset: \strlen($marker) + \strlen($matches[0]),
            content: $line->content,
            source: $line->source,
            offset: $line->offset,
            map: $line->map,
        );
    }
}
