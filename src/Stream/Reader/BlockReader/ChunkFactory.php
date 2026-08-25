<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

use FFI\Preprocessor\Stream\Reader\BlockReader\ChunkFactory\Splicer;
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
     * Matches the white space and the block comments after `#`, the name of
     * the directive (an identifier of the Latin letters, digits, underscores
     * and multibyte UTF-8 characters) and the white space and the comments
     * after the name. The name is absent from the null directive and from a
     * line such as `# 42 "file.h"`.
     *
     * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.4.3 Identifiers
     */
    private const NAME_PATTERN = '~\G(?:[ \t\f\x0B]++|/\*.*?\*/)*+'
        . '([A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*+)?'
        . '(?:[ \t\f\x0B]++|/\*.*?\*/)*+~s';

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
     */
    public function createSourceChunk(string $fragment, int $offset): SourceChunk
    {
        $splices = $this->splicer->createSourceMap($fragment);

        if (!$splices->isEmpty()) {
            $fragment = $this->splicer->removeSplices($fragment);
        }

        return new SourceChunk($fragment, $this->file, $offset, $splices);
    }

    /**
     * @param non-empty-string $fragment the line of the directive from `#` or
     *        `%:`, the new-line excluded
     * @param int<0, max> $offset offset of the fragment in the file
     */
    public function createDirective(string $fragment, int $offset): SourceDirective
    {
        $chunk = $this->createSourceChunk($fragment, $offset);
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
            splices: $chunk->splices,
        );
    }
}
