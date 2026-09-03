<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

use FFI\Preprocessor\Stream\SourceChunk;
use FFI\Preprocessor\Stream\SourceDirective;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Turns the fragments read from a file into the chunks: the line splices
 * are removed, a directive is split into its name and body.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class ChunkFactory
{
    /**
     * Matches the white space after `#`, the name of the directive (an
     * identifier of the Latin letters, digits, underscores and multibyte UTF-8
     * characters) and the white space after the name. The name is absent from
     * the null directive and from a line such as `# 42 "file.h"`.
     *
     * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.3 Identifiers
     */
    private const NAME_PATTERN = '~\G[ \t\f\x0B]*+([A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*+)?[ \t\f\x0B]*+~';

    public function __construct(
        /**
         * The file the fragments have been read from
         */
        private readonly FileInterface $file,
        /**
         * Removes the line splices from the fragments
         */
        private readonly Splicer $splicer = new Splicer(),
    ) {}

    /**
     * @param int<0, max> $offset offset of the fragment in the file
     * @param list<int<1, max>> $commentOffsets offsets in the fragment of the bytes after the spaces that
     *        replace the comments
     * @param list<int<1, max>> $commentSizes number of the bytes each comment takes in the file besides its space
     */
    public function createSourceChunk(
        string $fragment,
        int $offset,
        array $commentOffsets = [],
        array $commentSizes = [],
    ): SourceChunk {
        $map = $this->splicer->createSourceMap($fragment, $commentOffsets, $commentSizes);

        return new SourceChunk($this->splicer->removeSplices($fragment), $this->file, $offset, $map);
    }

    /**
     * @param non-empty-string $fragment the line of the directive from `#` or
     *        `%:`, the new-line excluded, the comments replaced
     * @param int<0, max> $offset offset of the fragment in the file
     * @param list<int<1, max>> $commentOffsets offsets in the fragment of the bytes after the spaces that
     *        replace the comments
     * @param list<int<1, max>> $commentSizes number of the bytes each comment takes in the file besides its space
     */
    public function createDirective(
        string $fragment,
        int $offset,
        array $commentOffsets = [],
        array $commentSizes = [],
    ): SourceDirective {
        $chunk = $this->createSourceChunk($fragment, $offset, $commentOffsets, $commentSizes);
        $line = $chunk->content;

        // Past "#" or "%:", the pattern matches at least an empty string there
        $start = $line[0] === '#' ? 1 : 2;
        \preg_match(self::NAME_PATTERN, $line, $matches, 0, $start);

        return new SourceDirective(
            name: $matches[1] ?? '',
            bodyOffset: $start + \strlen($matches[0] ?? ''),
            content: $line,
            source: $this->file,
            offset: $offset,
            map: $chunk->map,
        );
    }
}
