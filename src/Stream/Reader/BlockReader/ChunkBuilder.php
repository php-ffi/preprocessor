<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

use FFI\Preprocessor\Stream\Reader\BlockReader\CommentReplacer\Replacement;
use FFI\Preprocessor\Stream\SourceChunk;
use FFI\Preprocessor\Stream\SourceDirective;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Collects the source text a reader meets between the directives and tells
 * whether a `#` met next starts a directive: only white space and comments
 * may precede it on its line, a comment being a single space (translation
 * phase 3).
 *
 * Lives for a single reading of a file.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class ChunkBuilder
{
    /**
     * White space allowed before `#` on the line of a directive.
     */
    private const BLANKS = " \t\f\v";

    /**
     * Whether only white space and comments precede the end of the collected
     * text on its line
     *
     * @phpstan-readonly-allow-private-mutation
     */
    public bool $isLineStart = true;

    /**
     * The source text collected since the last directive
     *
     * @phpstan-readonly-allow-private-mutation
     */
    public string $text = '';

    /**
     * Offset in the file of {@see $text}
     *
     * @var int<0, max>
     */
    private int $offset;

    /**
     * Offsets in {@see $text} of the bytes after the spaces that replace the comments
     *
     * @var list<int<1, max>>
     */
    private array $commentOffsets = [];

    /**
     * Number of the bytes each comment takes in the file besides its space, of the same index as its offset
     *
     * @var list<int<1, max>>
     */
    private array $commentSizes = [];

    /**
     * Offset in {@see $text} right after its last line feed of source text,
     * not in a comment and not of a line splice, `0` in case of there is none
     *
     * @var int<0, max>
     */
    private int $lineEnd = 0;

    /**
     * Creates the chunks of the file
     */
    private readonly ChunkFactory $factory;

    /**
     * @param int<0, max> $offset offset in the file the text starts at
     */
    public function __construct(
        FileInterface $file,
        int $offset = 0,
        /**
         * Tells the line splices
         */
        private readonly Splicer $splicer = new Splicer(),
    ) {
        $this->factory = new ChunkFactory($file, $splicer);
        $this->offset = $offset;
    }

    /**
     * Adds a fragment of source text without comments and literals in it.
     */
    public function appendText(string $text): void
    {
        if ($text === '') {
            return;
        }

        $lineEnd = $this->splicer->findLastLineEnd($text);

        if ($lineEnd !== null) {
            $this->lineEnd = \strlen($this->text) + $lineEnd;
        }

        $this->text .= $text;
        $this->isLineStart = $this->isLineStartAfter($text, $this->isLineStart);
    }

    /**
     * Adds a text with its comments replaced.
     */
    public function appendReplacedText(Replacement $replacement): void
    {
        $length = \strlen($this->text);

        foreach ($replacement->offsets as $i => $offset) {
            $this->commentOffsets[] = $length + $offset;
            $this->commentSizes[] = $replacement->sizes[$i];
        }

        $this->appendText($replacement->text);
    }

    /**
     * Adds a block or a line comment as the space it is replaced by, which
     * keeps a line start.
     */
    public function appendComment(string $comment): void
    {
        /** @var int<1, max> */
        $size = \strlen($comment) - 1;

        $this->commentOffsets[] = \strlen($this->text) + 1;
        $this->commentSizes[] = $size;
        $this->text .= ' ';
    }

    /**
     * Adds a literal, or a `#` that does not start a directive.
     */
    public function appendToken(string $token): void
    {
        $this->text .= $token;
        $this->isLineStart = false;
    }

    /**
     * Returns the text collected so far, the blanks before the directive met
     * excluded, {@see null} in case of there is none.
     */
    public function takeTextBeforeDirective(): ?SourceChunk
    {
        $this->text = \rtrim($this->text, self::BLANKS);

        // A space that is trimmed takes its comment away
        $length = \strlen($this->text);

        while ($this->commentOffsets !== [] && \end($this->commentOffsets) > $length) {
            \array_pop($this->commentOffsets);
            \array_pop($this->commentSizes);
        }

        return $this->takeText();
    }

    /**
     * Returns the text collected so far, {@see null} in case of there is none.
     */
    public function takeText(): ?SourceChunk
    {
        if ($this->text === '') {
            return null;
        }

        $chunk = $this->factory->createSourceChunk(
            $this->text,
            $this->offset,
            $this->commentOffsets,
            $this->commentSizes,
        );
        $this->text = '';
        $this->commentOffsets = [];
        $this->commentSizes = [];
        $this->lineEnd = 0;

        return $chunk;
    }

    /**
     * Returns the complete lines of the text collected so far, the text of
     * the last line stays collected, {@see null} in case of there is no
     * complete line.
     */
    public function takeCompleteLines(): ?SourceChunk
    {
        if ($this->lineEnd === 0) {
            return null;
        }

        // The comments up to the end of the line stay in the chunk
        $count = 0;
        $skipped = 0;

        while (isset($this->commentOffsets[$count]) && $this->commentOffsets[$count] <= $this->lineEnd) {
            $skipped += $this->commentSizes[$count];
            ++$count;
        }

        $chunk = $this->factory->createSourceChunk(
            \substr($this->text, 0, $this->lineEnd),
            $this->offset,
            \array_slice($this->commentOffsets, 0, $count),
            \array_slice($this->commentSizes, 0, $count),
        );

        /** @var list<int<1, max>> $offsets */
        $offsets = \array_map(
            fn(int $offset): int => $offset - $this->lineEnd,
            \array_slice($this->commentOffsets, $count),
        );

        $this->commentOffsets = $offsets;
        $this->commentSizes = \array_slice($this->commentSizes, $count);
        $this->text = \substr($this->text, $this->lineEnd);
        $this->offset += $this->lineEnd + $skipped;
        $this->lineEnd = 0;

        return $chunk;
    }

    /**
     * Creates the directive and starts the text after it.
     *
     * @param non-empty-string $line the line of the directive from `#` or `%:`,
     *        the new-line excluded
     * @param int<0, max> $offset offset of the line in the file
     * @param int<0, max> $next offset in the file of the text after the line
     * @param list<int<1, max>> $commentOffsets offsets in the line of the bytes after the spaces that replace the
     *        comments
     * @param list<int<1, max>> $commentSizes number of the bytes each comment takes in the file besides its space
     */
    public function createDirective(
        string $line,
        int $offset,
        int $next,
        array $commentOffsets = [],
        array $commentSizes = [],
    ): SourceDirective {
        $this->text = '';
        $this->commentOffsets = [];
        $this->commentSizes = [];
        $this->offset = $next;
        $this->lineEnd = 0;
        $this->isLineStart = true;

        return $this->factory->createDirective($line, $offset, $commentOffsets, $commentSizes);
    }

    /**
     * Whether only white space and comments precede the end of the fragment
     * on its line. The new-line of a line splice does not start a line.
     *
     * @param bool $isLineStart whether only white space and comments precede
     *        the start of the fragment on its line
     */
    private function isLineStartAfter(string $fragment, bool $isLineStart): bool
    {
        $end = \strlen($fragment);

        while (($newLine = self::findLastNewLine($fragment, $end)) !== null) {
            if (!$this->isBlank(\substr($fragment, $newLine + 1, $end - $newLine - 1))) {
                return false;
            }

            $splice = $this->splicer->findSpliceStart($fragment, $newLine);

            if ($splice === null) {
                return true;
            }

            // The line continues the previous one, the splice is not a part of it
            $end = $splice;
        }

        return $isLineStart && $this->isBlank(\substr($fragment, 0, $end));
    }

    /**
     * Returns the offset of the last `\n` or `\r` of the fragment before the
     * given offset, {@see null} in case of there is none.
     *
     * @param int<0, max> $end
     * @return int<0, max>|null
     */
    private static function findLastNewLine(string $fragment, int $end): ?int
    {
        if ($end === 0) {
            return null;
        }

        // A negative offset limits the search to the bytes before the end
        $limit = $end - \strlen($fragment) - 1;
        /** @var int<0, max>|false */
        $lineFeed = \strrpos($fragment, "\n", $limit);
        /** @var int<0, max>|false */
        $carriageReturn = \strrpos($fragment, "\r", $limit);

        if ($lineFeed === false) {
            return $carriageReturn === false ? null : $carriageReturn;
        }

        return $carriageReturn === false ? $lineFeed : \max($lineFeed, $carriageReturn);
    }

    /**
     * Whether the text consists of white space and line splices only.
     */
    private function isBlank(string $text): bool
    {
        $text = $this->splicer->removeSplices($text);

        return \strspn($text, self::BLANKS) === \strlen($text);
    }
}
