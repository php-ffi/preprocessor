<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Stream\Reader\BlockReader;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\Reader\BlockReader\CommentReplacer\Expectation;
use FFI\Preprocessor\Stream\Reader\BlockReader\CommentReplacer\Replacement;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Replaces the comments of a line of a directive by a space each
 * (translation phase 3), the header names aside: `#include <a/*b.h>` holds no
 * comment.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor\Stream\Reader
 */
final class CommentReplacer
{
    /**
     * The tokens of the markup grammar (`resources/markup.pp3`), line splices
     * allowed between the bytes of every one of them.
     */
    private const DEFINITIONS = <<<'REGEX'
        (?(DEFINE)
            (?<splice>\\[\x20\t\f\x0B]*+(?:\r\n|\n|\r))
            (?<splices>(?&splice)*+)
            (?<hex>(?&splices)[0-9A-Fa-f])
            (?<ucn>\\(?&splices)(?:u(?&hex){4}|U(?&hex){8}))
            (?<identifier>(?:[A-Za-z_\x80-\xFF]|(?&ucn))(?:(?&splices)(?:[0-9A-Za-z_\x80-\xFF]|(?&ucn)))*+)
            (?<number>(?:\.(?&splices))?[0-9](?:(?&splices)(?:[eEpP](?&splices)[+-]|[0-9A-Za-z_\x80-\xFF.]|(?&ucn)|'(?&splices)[0-9A-Za-z_]))*+)
            (?<escape>(?&splice)|\\(?&splices)[^\r\n])
            (?<string>"(?:[^"\\\r\n]++|(?&escape))*+"?)
            (?<char>'(?:[^'\\\r\n]++|(?&escape))*+'?)
            (?<block>/(?&splices)\*(?:[^*]++|\*(?!(?&splices)/))*+(?:\*(?&splices)/|\z))
            (?<line>/(?&splices)/(?:[^\r\n\\]++|(?&escape))*+)
        )
        REGEX;

    /**
     * A token of a line of a directive that is not a header name.
     */
    private const TOKEN_PATTERN = '~' . self::DEFINITIONS . <<<'REGEX'
        \G(?:
            (?<comment>(?&block)|(?&line))
            |(?<blank>[\x20\t\f\x0B]++|(?&splice))
            |(?<name>(?&identifier))
            |(?&number)
            |[^\r\n\\"'/A-Za-z0-9_\x80-\xFF.]++
            |\.
            |(?&escape)
            |(?&string)
            |(?&char)
            |\r\n|\n|\r
            |.
        )~xsD
        REGEX;

    /**
     * Everything of a line of a directive up to the first comment, the line
     * holds no header name past this point.
     */
    private const PLAIN_PATTERN = '~' . self::DEFINITIONS . <<<'REGEX'
        \G(?:
            (?&identifier)
            |(?&number)
            |[^\r\n\\"'/A-Za-z0-9_\x80-\xFF.]++
            |\.
            |(?&escape)
            |(?&string)
            |(?&char)
            |/(?!(?&splices)[*/])
            |\r\n|\n|\r
        )++~xsD
        REGEX;

    /**
     * 6.4.8 Header names, read as they are written.
     */
    private const HEADER_PATTERN = '~' . self::DEFINITIONS . <<<'REGEX'
        \G(?:<(?:[^>\r\n\\]++|(?&splice)|\\)*+>?|"(?:[^"\r\n\\]++|(?&splice)|\\)*+"?)~xsD
        REGEX;

    /**
     * `#` or `%:` that starts the line of a directive.
     */
    private const MARKER_PATTERN = '~' . self::DEFINITIONS . <<<'REGEX'
        \G(?:\#|%(?&splices):)~xsD
        REGEX;

    private const PARENTHESIS_PATTERN = '~\G\(~D';

    /**
     * The directives whose operand is a header name.
     */
    private const HEADER_DIRECTIVES = ['include', 'include_next', 'import', 'embed'];

    /**
     * The directives whose expression may hold a header name.
     */
    private const CONDITION_DIRECTIVES = ['if', 'elif'];

    /**
     * The operators whose operand is a header name.
     */
    private const HEADER_OPERATORS = ['__has_include', '__has_include_next', '__has_embed'];

    public function __construct(
        /**
         * Removes the line splices from the names
         */
        private readonly Splicer $splicer = new Splicer(),
    ) {}

    /**
     * Returns the line with its comments replaced.
     *
     * @param string $line the line of a directive from `#` or `%:`
     * @param int<0, max> $offset offset of the line in the file
     * @throws SyntaxErrorException in case of a block comment is not closed
     * @throws InternalException in case of the line cannot be matched
     */
    public function replace(string $line, FileInterface $file, int $offset): Replacement
    {
        if (!\str_contains($line, '/')) {
            return new Replacement($line);
        }

        return BacktrackLimit::run(fn(): Replacement => $this->replaceComments($line, $file, $offset));
    }

    /**
     * @param int<0, max> $offset
     * @throws SyntaxErrorException in case of a block comment is not closed
     * @throws InternalException in case of the line cannot be matched
     */
    private function replaceComments(string $line, FileInterface $file, int $offset): Replacement
    {
        $text = '';
        $offsets = [];
        $sizes = [];
        $position = 0;
        $length = \strlen($line);
        $expectation = Expectation::DirectiveName;

        $marker = $this->match(self::MARKER_PATTERN, $line, 0);

        if ($marker !== null) {
            $text = $marker;
            $position = \strlen($text);
        }

        while ($position < $length) {
            $header = $this->findHeader($line, $position, $expectation);

            if ($header !== null) {
                $text .= $header;
                $position += \strlen($header);
                $expectation = $expectation === Expectation::DirectiveHeader ? Expectation::Rest : Expectation::Condition;

                continue;
            }

            if ($expectation === Expectation::Rest) {
                $plain = $this->match(self::PLAIN_PATTERN, $line, $position);

                if ($plain !== null) {
                    $text .= $plain;
                    $position += \strlen($plain);

                    continue;
                }
            }

            $parenthesis = $this->findParenthesis($line, $position, $expectation);

            if ($parenthesis !== null) {
                $text .= $parenthesis;
                $position += \strlen($parenthesis);
                $expectation = Expectation::OperatorHeader;

                continue;
            }

            [$piece, $isComment, $isBlank, $name] = $this->matchToken($line, $position);

            $position += \strlen($piece);

            if ($isComment) {
                /** @var int<0, max> $start */
                $start = $offset + $position - \strlen($piece);
                /** @var int<1, max> $size */
                $size = \strlen($piece) - 1;

                $this->assertClosed($piece, $file, $start);

                $text .= ' ';
                $offsets[] = \strlen($text);
                $sizes[] = $size;

                continue;
            }

            $text .= $piece;

            if (!$isBlank) {
                $expectation = $this->getNextExpectation($expectation, $name);
            }
        }

        return new Replacement($text, $offsets, $sizes);
    }

    /**
     * Returns the header name at the position, {@see null} in case of the
     * line holds none there.
     */
    private function findHeader(string $line, int $position, Expectation $expectation): ?string
    {
        if ($expectation !== Expectation::DirectiveHeader && $expectation !== Expectation::OperatorHeader) {
            return null;
        }

        return $this->match(self::HEADER_PATTERN, $line, $position);
    }

    /**
     * Returns the parenthesis of an operator at the position, {@see null} in
     * case of there is none.
     */
    private function findParenthesis(string $line, int $position, Expectation $expectation): ?string
    {
        if ($expectation !== Expectation::OperatorParenthesis) {
            return null;
        }

        return $this->match(self::PARENTHESIS_PATTERN, $line, $position);
    }

    /**
     * Returns the text the pattern matches at the position, {@see null} in
     * case of there is no match.
     *
     * @param non-empty-string $pattern
     * @throws InternalException in case of the match fails
     */
    private function match(string $pattern, string $line, int $position): ?string
    {
        if (\preg_match($pattern, $line, $matches, 0, $position) === 1) {
            return $matches[0];
        }

        if (\preg_last_error() !== \PREG_NO_ERROR) {
            throw $this->createMatchFailure();
        }

        return null;
    }

    /**
     * Returns the token at the position.
     *
     * @return array{string, bool, bool, string|null} the text of the token, whether it is a comment, whether
     *         it is blank, the identifier in case of it is one
     * @throws InternalException in case of the match fails
     */
    private function matchToken(string $line, int $position): array
    {
        if (\preg_match(self::TOKEN_PATTERN, $line, $matches, \PREG_UNMATCHED_AS_NULL, $position) !== 1) {
            // Every byte is a token, so the match only fails with an error
            throw $this->createMatchFailure();
        }

        return [$matches[0] ?? '', ($matches['comment'] ?? null) !== null, ($matches['blank'] ?? null) !== null, $matches['name'] ?? null];
    }

    private function createMatchFailure(): InternalException
    {
        return InternalException::becauseInternalErrorOccurs(\sprintf(
            'The line of a directive cannot be matched: "%s"',
            \addcslashes(\preg_last_error_msg(), '"'),
        ));
    }

    /**
     * Returns what the token after the one given may be.
     *
     * @param string|null $name the identifier, {@see null} in case of the
     *        token is not one
     */
    private function getNextExpectation(Expectation $expectation, ?string $name): Expectation
    {
        if ($name !== null) {
            $name = $this->splicer->removeSplices($name);
        }

        return match ($expectation) {
            Expectation::DirectiveName => match (true) {
                \in_array($name, self::HEADER_DIRECTIVES, true) => Expectation::DirectiveHeader,
                \in_array($name, self::CONDITION_DIRECTIVES, true) => Expectation::Condition,
                default => Expectation::Rest,
            },
            Expectation::Condition, Expectation::OperatorParenthesis, Expectation::OperatorHeader => match (true) {
                \in_array($name, self::HEADER_OPERATORS, true) => Expectation::OperatorParenthesis,
                default => Expectation::Condition,
            },
            Expectation::DirectiveHeader, Expectation::Rest => Expectation::Rest,
        };
    }

    /**
     * @param int<0, max> $offset
     * @throws SyntaxErrorException in case of the block comment is not closed
     */
    private function assertClosed(string $comment, FileInterface $file, int $offset): void
    {
        $comment = $this->splicer->removeSplices($comment);

        // A source file does not end in a partial comment (5.1.1.2p3), GCC fails
        if (\str_starts_with($comment, '/*') && (\strlen($comment) < 4 || !\str_ends_with($comment, '*/'))) {
            throw new SyntaxErrorException('Unterminated comment', $file, $offset);
        }
    }
}
