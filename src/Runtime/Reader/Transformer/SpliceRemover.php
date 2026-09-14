<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime\Reader\Transformer;

use FFI\Preprocessor\Runtime\Chunk\SourceMap\Edit;
use FFI\Preprocessor\Runtime\Chunk\UntaggedChunk;
use FFI\Preprocessor\Runtime\Exception\UnmatchedTextException;

/**
 * Translation phase 2: removes each backslash followed by a new-line, blanks
 * between them tolerated as GCC and Clang do.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Runtime
 */
final class SpliceRemover implements TransformerInterface
{
    private const SPLICE_PATTERN = '~\\\\[ \t\f\x0B]*+(?:\r\n|\n|\r)~';

    /**
     * A splice the file has not finished yet: its new-line is the next byte.
     */
    private const UNFINISHED_PATTERN = '~\G\\\\[ \t\f\x0B]*+\r?\z~';

    /**
     * @throws UnmatchedTextException in case of the text cannot be matched
     */
    public function transform(UntaggedChunk $input, bool $isFinal): Transformation
    {
        $backslash = \strrpos($input->content, '\\');

        if ($backslash === false) {
            return new Transformation($input, $input->slice(0, 0));
        }

        $end = \strlen($input->content);

        if (!$isFinal && \preg_match(self::UNFINISHED_PATTERN, $input->content, offset: $backslash) === 1) {
            $end = $backslash;
        }

        $ready = $input->slice(0, $end);

        return new Transformation($ready->edit($this->getSplices($ready->content)), $input->slice($end));
    }

    /**
     * @return list<Edit>
     * @throws UnmatchedTextException in case of the text cannot be matched
     */
    private function getSplices(string $text): array
    {
        if (\preg_match_all(self::SPLICE_PATTERN, $text, $matches, \PREG_OFFSET_CAPTURE) === false) {
            throw UnmatchedTextException::becauseTextIsNotMatched('line splices');
        }

        /** @var list<array{string, int<0, max>}> $splices */
        $splices = $matches[0];

        return \array_map(static fn(array $splice): Edit => new Edit($splice[1], \strlen($splice[0])), $splices);
    }
}
