<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\Transformer;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\Chunk\SourceMap\Edit;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;

/**
 * Translation phase 3: replaces each comment by one space. The comment
 * markers inside the literals and inside the header names (`#include <a//b.h>`,
 * `__has_include(<a/*b.h>)`) are not comments.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream
 */
final class CommentReplacer implements TransformerInterface
{
    /**
     * Block comment, closed by the end of the text if it is not closed.
     */
    private const BLOCK = '/\*(?:[^*]++|\*(?!/))*+(?:\*/|\z)';

    /**
     * Blanks and block comments between the tokens of a directive line.
     */
    private const GAP = '(?:[\x20\t\f\x0B]++|(?&block))*+';

    private const IDENTIFIER = '[A-Za-z_\x80-\xFF][0-9A-Za-z_\x80-\xFF]*+';

    /**
     * Preprocessing number with the digit separators.
     */
    private const NUMBER = '\.?[0-9](?:[eEpP][+-]|\'[0-9A-Za-z_]|[0-9A-Za-z_.\x80-\xFF])*+';

    private const STRING = '"(?:[^"\\\\\r\n]++|\\\\.?)*+"?';

    private const CHAR = '\'(?:[^\'\\\\\r\n]++|\\\\.?)*+\'?';

    private const HEADER_NAME = '<[^>\r\n]*+>|"[^"\r\n]*+"';

    /**
     * The part of an `#include`-like directive before its header name.
     */
    private const DIRECTIVE_LEAD = '(?m:^)(?&gap)(?:\#|%:)(?&gap)(?:include_next|include|import|embed)'
        . '(?![0-9A-Za-z_\x80-\xFF])(?&gap)';

    /**
     * The part of a `__has_include`-like operator before its header name.
     */
    private const OPERATOR_LEAD = '__has_(?:include_next|include|embed)(?![0-9A-Za-z_\x80-\xFF])(?&gap)\((?&gap)';

    /**
     * Block comment that may be not closed, and line comment.
     */
    private const COMMENT = '/\*(?:[^*]++|\*(?!/))*+(?:\*/|(?<unclosed>\z))|//[^\r\n]*+';

    /**
     * The comments and the places a header name follows.
     */
    private const PATTERN = '~(*ANYCRLF)'
        . '(?(DEFINE)'
        . '(?<block>' . self::BLOCK . ')'
        . '(?<gap>' . self::GAP . ')'
        . '(?<identifier>' . self::IDENTIFIER . ')'
        . '(?<number>' . self::NUMBER . ')'
        . '(?<string>' . self::STRING . ')'
        . '(?<char>' . self::CHAR . ')'
        . '(?<name>' . self::HEADER_NAME . ')'
        . ')'
        . '(?:'
        . '(?<lead>' . self::DIRECTIVE_LEAD . '|' . self::OPERATOR_LEAD . ')(?&name)'
        . '|(?:(?&string)|(?&char)|(?&number)|(?&identifier))(*SKIP)(*FAIL)'
        . '|(?<comment>' . self::COMMENT . ')'
        . ')~';

    private const BLOCK_PATTERN = '~/\*(?:[^*]++|\*(?!/))*+\*/~';

    /**
     * @throws SyntaxErrorException in case of the last chunk of the file ends in a block comment that is not closed
     * @throws InternalException in case of the text cannot be matched
     */
    public function transform(UntaggedChunk $input, bool $isFinal): Transformation
    {
        $limit = \strlen($input->content);

        if (!$isFinal) {
            $limit = $input->findLastLineEnd() ?? 0;
        }

        $edits = [];
        $spans = [];

        foreach ($this->getMatches($input->content) as $match) {
            $start = $match[0][1];

            if ($start >= $limit && !$isFinal) {
                break;
            }

            $end = $start + \strlen($match[0][0]);

            if (isset($match['lead']) && $match['lead'][1] >= 0) {
                foreach ($this->getHeaderEdits($match['lead'][0], $match['lead'][1]) as $edit) {
                    $edits[] = $edit;
                }
            } elseif (isset($match['unclosed'])) {
                if ($isFinal) {
                    throw SyntaxErrorException::becauseCommentIsNotClosed($input->source, $input->getPhysicalOffset($start));
                }

                $end = \PHP_INT_MAX;
            } else {
                $edits[] = new Edit($start, \strlen($match[0][0]), ' ');
            }

            $spans[] = [$start, $end];
        }

        return $this->split($input, $this->getSafeEnd($input, $limit, $spans), $edits);
    }

    /**
     * Returns the edits of the comments in the part of the line that precedes
     * a header name.
     *
     * @param int<0, max> $offset offset of the part in the text
     * @return list<Edit>
     */
    private function getHeaderEdits(string $lead, int $offset): array
    {
        \preg_match_all(self::BLOCK_PATTERN, $lead, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        /** @var list<array{array{string, int<0, max>}}> $matches */
        return \array_map(
            static fn(array $match): Edit => new Edit($offset + $match[0][1], \strlen($match[0][0]), ' '),
            $matches,
        );
    }

    /**
     * Returns the end of the part of the text that no comment and no place a
     * header name follows crosses, not further than the limit.
     *
     * @param int<0, max> $limit
     * @param list<array{int<0, max>, int}> $spans start and end of each match, in ascending order, the end of a
     *        block comment that is not closed is the largest integer
     * @return int<0, max>
     */
    private function getSafeEnd(UntaggedChunk $input, int $limit, array $spans): int
    {
        for ($i = \count($spans) - 1; $i >= 0; --$i) {
            [$start, $end] = $spans[$i];

            if ($start < $limit && $end > $limit) {
                $limit = $input->getLineStart($start);
            }
        }

        return $limit;
    }

    /**
     * @param list<Edit> $edits
     * @param int<0, max> $limit
     */
    private function split(UntaggedChunk $input, int $limit, array $edits): Transformation
    {
        $kept = [];

        foreach ($edits as $edit) {
            if ($edit->offset + $edit->length <= $limit) {
                $kept[] = $edit;
            }
        }

        return new Transformation($input->slice(0, $limit)->edit($kept), $input->slice($limit));
    }

    /**
     * @return list<array{0: array{string, int<0, max>}, lead?: array{string, int}, unclosed?: array{string, int}}>
     * @throws InternalException in case of the text cannot be matched
     */
    private function getMatches(string $text): array
    {
        if (!\str_contains($text, '/')) {
            return [];
        }

        if (\preg_match_all(self::PATTERN, $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE) === false) {
            throw InternalException::becauseTextIsNotMatched('comments');
        }

        /** @var list<array{0: array{string, int<0, max>}, lead?: array{string, int}, unclosed?: array{string, int}}> */
        return $matches;
    }
}
