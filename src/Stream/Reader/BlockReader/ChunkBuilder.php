<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

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
     * Creates the chunks of the file
     */
    private readonly ChunkFactory $factory;

    /**
     * @param int<0, max> $offset offset in the file the text starts at
     */
    public function __construct(FileInterface $file, int $offset = 0)
    {
        $this->factory = new ChunkFactory($file);
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

        $this->text .= $text;
        $this->isLineStart = self::isLineStartAfter($text, $this->isLineStart);
    }

    /**
     * Adds a block or a line comment, which keeps a line start.
     */
    public function appendComment(string $comment): void
    {
        $this->text .= $comment;
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

        $chunk = $this->factory->createSourceChunk($this->text, $this->offset);
        $this->text = '';

        return $chunk;
    }

    /**
     * Creates the directive and starts the text after it.
     *
     * @param non-empty-string $line the line of the directive from `#` or `%:`,
     *        the new-line excluded
     * @param int<0, max> $offset offset of the line in the file
     * @param int<0, max> $next offset in the file of the text after the line
     */
    public function createDirective(string $line, int $offset, int $next): SourceDirective
    {
        $this->text = '';
        $this->offset = $next;
        $this->isLineStart = true;

        return $this->factory->createDirective($line, $offset);
    }

    /**
     * Whether only white space and comments precede the end of the fragment
     * on its line. A new-line right after a backslash is a line splice and
     * does not start a line.
     *
     * @param bool $isLineStart whether only white space and comments precede
     *        the start of the fragment on its line
     */
    private static function isLineStartAfter(string $fragment, bool $isLineStart): bool
    {
        $end = \strlen($fragment);

        while (($newLine = self::findLastNewLine($fragment, $end)) !== null) {
            if (!self::isBlank(\substr($fragment, $newLine + 1, $end - $newLine - 1))) {
                return false;
            }

            if (!self::isSplice($fragment, $newLine)) {
                return true;
            }

            // The line continues the previous one
            $end = $newLine;
        }

        return $isLineStart && self::isBlank(\substr($fragment, 0, $end));
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
        /** @var int<0, max>|false $lineFeed */
        $lineFeed = \strrpos($fragment, "\n", $limit);
        /** @var int<0, max>|false $carriageReturn */
        $carriageReturn = \strrpos($fragment, "\r", $limit);

        if ($lineFeed === false) {
            return $carriageReturn === false ? null : $carriageReturn;
        }

        return $carriageReturn === false ? $lineFeed : \max($lineFeed, $carriageReturn);
    }

    /**
     * Whether the new-line at the given offset ends a line splice: a
     * backslash right before it, or before the `\r` of `\r\n`.
     *
     * @param int<0, max> $offset
     */
    private static function isSplice(string $fragment, int $offset): bool
    {
        if ($fragment[$offset] === "\n" && $offset > 0 && $fragment[$offset - 1] === "\r") {
            --$offset;
        }

        return $offset > 0 && $fragment[$offset - 1] === '\\';
    }

    /**
     * Whether the text consists of white space and line splices only.
     */
    private static function isBlank(string $text): bool
    {
        $text = \str_replace(["\\\r\n", "\\\n", "\\\r"], '', $text);

        return \strspn($text, self::BLANKS) === \strlen($text);
    }
}
