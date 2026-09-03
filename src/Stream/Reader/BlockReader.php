<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\Reader\BlockReader\BacktrackLimit;
use FFI\Preprocessor\Stream\Reader\BlockReader\ChunkBuilder;
use FFI\Preprocessor\Stream\Reader\BlockReader\CommentReplacer;
use FFI\Preprocessor\Stream\Reader\BlockReader\CompiledMarkupLexer;
use FFI\Preprocessor\Stream\Reader\BlockReader\Splicer;
use FFI\Preprocessor\Stream\SourceChunk;
use FFI\Preprocessor\Stream\SourceDirective;
use Phplrt\Contracts\Lexer\Exception\LexerExceptionInterface;
use Phplrt\Contracts\Lexer\Exception\RuntimeExceptionInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Lexer\Token\Token;
use Phplrt\Source\StringSource;

/**
 * Streaming reader: keeps no more of a file in memory than a block and the
 * construction that crosses its end. Reads the files in any ASCII-compatible
 * encoding, an UTF-8 byte order mark at the start of a file is not a part of
 * its text.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 6.10.1 General
 */
final class BlockReader implements ReaderInterface
{
    /**
     * UTF-8 byte order mark.
     */
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * Number of bytes the lexer may look at past the end of a token to tell
     * where it ends, such as the 10 bytes of the universal character name
     * `\U0001F600`, line splices aside. A token that ends closer to the end
     * of the buffer, or to the line splices the buffer ends with, may
     * continue in the next block.
     */
    private const LOOKAHEAD = 16;

    /**
     * Replaces the comments of the lines of the directives
     */
    private readonly CommentReplacer $replacer;

    public function __construct(
        /**
         * Number of bytes read from a file at a time
         *
         * @var int<1, max>
         */
        private readonly int $blockSize = 65_536,
        /**
         * Splits the blocks into the tokens of the markup
         */
        private readonly CompiledMarkupLexer $lexer = new CompiledMarkupLexer(),
        /**
         * Tells the line splices
         */
        private readonly Splicer $splicer = new Splicer(),
    ) {
        $this->replacer = new CommentReplacer($splicer);
    }

    /**
     * @throws InternalException in case of the file cannot be read
     * @throws SyntaxErrorException
     */
    public function read(FileInterface $file): iterable
    {
        try {
            $buffer = $file->read(0, $this->blockSize);
        } catch (SourceExceptionInterface $e) {
            throw InternalException::becauseSourceIsNotReadable($file, $e);
        }

        return $this->createChunks($file, $buffer);
    }

    /**
     * @param string $buffer the first block of the file
     * @return \Generator<int, SourceChunk|SourceDirective>
     * @throws InternalException in case of the file cannot be read
     * @throws SyntaxErrorException in case of the file ends in a comment
     */
    private function createChunks(FileInterface $file, string $buffer): \Generator
    {
        // Offset in the file of the first byte of the buffer
        $base = 0;
        $isEnd = $buffer === '';

        try {
            // The byte order mark may be split by the blocks too
            while (!$isEnd && \strlen($buffer) < \strlen(self::BYTE_ORDER_MARK)) {
                $block = $file->read(\strlen($buffer), $this->blockSize);
                $isEnd = $block === '';
                $buffer .= $block;
            }

            if (\str_starts_with($buffer, self::BYTE_ORDER_MARK)) {
                $base = \strlen(self::BYTE_ORDER_MARK);
                $buffer = \substr($buffer, $base);
            }

            $builder = new ChunkBuilder($file, $base, $this->splicer);

            while (true) {
                // The tokens that end close to the end of the buffer may continue in the next block
                $consumed = 0;
                $limit = \strlen(\rtrim($buffer, "\\\r\n \t\f\v")) - self::LOOKAHEAD;

                foreach ($this->lex($buffer) as $token) {
                    if ($token->id < 0) {
                        break;
                    }

                    $end = $token->offset + $token->size;

                    if (!$isEnd && $end > $limit) {
                        $consumed += $this->appendCompleteLines($builder, $token);

                        break;
                    }

                    yield from $this->append($builder, $token, $base, $file);

                    $consumed = $end;
                }

                if ($isEnd) {
                    break;
                }

                // A long text without directives is put out by its complete lines
                if (\strlen($builder->text) >= $this->blockSize && ($lines = $builder->takeCompleteLines()) !== null) {
                    yield $lines;
                }

                $buffer = \substr($buffer, $consumed);
                $base += $consumed;

                $block = $file->read($base + \strlen($buffer), $this->blockSize);
                $isEnd = $block === '';
                $buffer .= $block;
            }
        } catch (SourceExceptionInterface|LexerExceptionInterface $e) {
            throw InternalException::becauseSourceIsNotReadable($file, $e);
        }

        $text = $builder->takeText();

        if ($text !== null) {
            yield $text;
        }
    }

    /**
     * Splits the buffer into the tokens of the markup. A logical line is a
     * single match whatever its length.
     *
     * @return list<TokenInterface>
     * @throws LexerExceptionInterface
     * @throws RuntimeExceptionInterface
     * @throws \Throwable
     */
    private function lex(string $buffer): array
    {
        return BacktrackLimit::run(function () use ($buffer): array {
            $tokens = [];

            foreach ($this->lexer->lex(new StringSource($buffer)) as $token) {
                $tokens[] = $token;
            }

            return $tokens;
        });
    }

    /**
     * Adds the token to the chunks being built, yields the chunks it
     * completes.
     *
     * @param int<0, max> $base offset in the file of the buffer the token has
     *        been read from
     * @return \Generator<int, SourceChunk|SourceDirective>
     * @throws SyntaxErrorException in case of a block comment is not closed
     */
    private function append(ChunkBuilder $builder, TokenInterface $token, int $base, FileInterface $file): \Generator
    {
        switch ($token->id) {
            case CompiledMarkupLexer::T_TEXT:
                $builder->appendText($token->value);
                break;

            case CompiledMarkupLexer::T_COMMENT:
                $comment = $this->splicer->removeSplices($token->value);

                // A source file does not end in a partial comment (5.1.1.2p3), GCC fails
                if (\strlen($comment) < 4 || !\str_ends_with($comment, '*/')) {
                    throw new SyntaxErrorException('Unterminated comment', $file, $base + $token->offset);
                }

                $builder->appendComment($token->value);
                break;

            case CompiledMarkupLexer::T_LINE_COMMENT:
                $builder->appendComment($token->value);
                break;

            case CompiledMarkupLexer::T_DIRECTIVE:
                \assert($token instanceof Token);

                // The new-line that ends the line is the only capturing group
                $newLine = $token->captures[0] ?? '';

                $line = \substr($token->value, 0, \strlen($token->value) - \strlen($newLine));
                $offset = $base + $token->offset;
                $replacement = $this->replacer->replace($line, $file, $offset);

                if (!$builder->isLineStart) {
                    $builder->appendReplacedText($replacement);
                    $builder->appendText($newLine);
                    break;
                }

                $text = $builder->takeTextBeforeDirective();

                if ($text !== null) {
                    yield $text;
                }

                \assert($replacement->text !== '');

                yield $builder->createDirective(
                    $replacement->text,
                    $offset,
                    $offset + $token->size,
                    $replacement->offsets,
                    $replacement->sizes,
                );
                break;

            case CompiledMarkupLexer::T_STRING:
            case CompiledMarkupLexer::T_CHAR:
                $builder->appendToken($token->value);
                break;
        }
    }

    /**
     * Adds the complete lines of a text that reaches the end of the buffer.
     * Returns the number of bytes added.
     *
     * @return int<0, max>
     */
    private function appendCompleteLines(ChunkBuilder $builder, TokenInterface $token): int
    {
        if ($token->id !== CompiledMarkupLexer::T_TEXT) {
            return 0;
        }

        // Nothing continues past a line feed: a lone carriage return may start "\r\n"
        $end = $this->splicer->findLastLineEnd($token->value);

        if ($end === null) {
            return 0;
        }

        $builder->appendText(\substr($token->value, 0, $end));

        return $end;
    }
}
