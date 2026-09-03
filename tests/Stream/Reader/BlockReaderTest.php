<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Reader;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\ChunkInterface;
use FFI\Preprocessor\Stream\Reader\BlockReader;
use FFI\Preprocessor\Stream\SourceChunk;
use FFI\Preprocessor\Stream\SourceDirective;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Source\FileSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(BlockReader::class)]
#[CoversClass(SourceDirective::class)]
final class BlockReaderTest extends TestCase
{
    /**
     * Sizes of the blocks the behaviour is checked with, the small ones split
     * every construction by the end of a block somewhere.
     */
    private const BLOCK_SIZES = [1, 2, 3, 5, 17, 65_536];

    /**
     * Each chunk is the text and its offset in the file, a directive also has
     * its name and body: `['text', 'int a;', 0]`, `['#define', 'X 1', 7]`.
     *
     * @return iterable<non-empty-string, array{string, list<list<int|string>>}>
     */
    public static function chunksDataProvider(): iterable
    {
        yield 'empty file' => ['', []];
        yield 'text only' => ["int a;\nint b;\n", [['text', "int a;\nint b;\n", 0]]];
        yield 'directive only' => ['#define X 1', [['#define', 'X 1', 0]]];
        yield 'directive between text' => [
            "int a;\n#define X 1\nint b;\n",
            [['text', "int a;\n", 0], ['#define', 'X 1', 7], ['text', "int b;\n", 19]],
        ];
        yield 'consecutive directives' => [
            "#ifdef X\n#endif\n",
            [['#ifdef', 'X', 0], ['#endif', '', 9]],
        ];
        yield 'blanks before hash' => [" \t#include <a.h>\n", [['#include', '<a.h>', 2]]];
        yield 'blanks after hash' => ["#  \tinclude <a.h>\n", [['#include', '<a.h>', 0]]];
        yield 'blanks before body' => ["#define \t X", [['#define', 'X', 0]]];
        yield 'body keeps blanks inside it' => ['#define F (x)  x ', [['#define', 'F (x)  x ', 0]]];
        yield 'hash inside the body' => ['#define S(x) #x', [['#define', 'S(x) #x', 0]]];
        yield 'hash after text on the line' => ["a = '#';\nx # y\n", [['text', "a = '#';\nx # y\n", 0]]];
        yield 'hash in a string literal' => ["s = \"#define X\";\n", [['text', "s = \"#define X\";\n", 0]]];
        yield 'null directive' => ["#\nint a;", [['#', '', 0], ['text', 'int a;', 2]]];
        yield 'non-directive without a name' => ['# 42 "a.h"', [['#', '42 "a.h"', 0]]];
        yield 'name is not a number' => ['#1define', [['#', '1define', 0]]];
        yield 'name of non-ASCII letters' => ["#d\xC3\xA9fine X", [["#d\xC3\xA9fine", 'X', 0]]];
        yield 'digraph of hash' => ["%:define X\n", [['#define', 'X', 0]]];
        yield 'digraph after text on the line' => ["a %: b\n", [['text', "a %: b\n", 0]]];
        yield 'paste operator at the start of a line' => ["## x\n%:%: y\n", [['text', "## x\n%:%: y\n", 0]]];
        yield 'hash followed by the digraph' => ["#%:\n", [['#', '%:', 0]]];
        yield 'hash, blank and hash' => ["# #\n", [['#', '#', 0]]];
        yield 'carriage return and line feed' => [
            "#define X\r\nint a;\r\n",
            [['#define', 'X', 0], ['text', "int a;\r\n", 11]],
        ];
        yield 'lone carriage return' => ["int a;\r#define X\rint b;", [
            ['text', "int a;\r", 0], ['#define', 'X', 7], ['text', 'int b;', 17],
        ]];
        yield 'byte order mark' => ["\xEF\xBB\xBF#define X\n", [['#define', 'X', 3]]];
        yield 'byte order mark before text' => ["\xEF\xBB\xBFint a;", [['text', 'int a;', 3]]];
        yield 'directive continued by splices' => [
            "#define X \\\n  1 \\\r\n  + 2\nint a;",
            [['#define', 'X   1   + 2', 0], ['text', 'int a;', 25]],
        ];
        yield 'name split by a splice' => ["#def\\\nine X\n", [['#define', 'X', 0]]];
        yield 'hash on a line continued from text' => ["int a; \\\n#define X\n", [['text', "int a; #define X\n", 0]]];
        yield 'splice in text' => ["in\\\nt a;\n#endif", [['text', "int a;\n", 0], ['#endif', '', 9]]];
        yield 'splice at the end of a directive' => ["#define X \\\n", [['#define', 'X ', 0]]];
        yield 'directive continued by a splice with blanks' => ["#define X 1 \\ \t\n 2\nint a;", [
            ['#define', 'X 1  2', 0],
            ['text', 'int a;', 19],
        ]];
        yield 'hash on a line continued by a splice with blanks' => ["int a; \\  \n#define X\n", [
            ['text', "int a; #define X\n", 0],
        ]];
        yield 'line comment continued by a splice with blanks' => ["// a \\ \n#define X\nint a;", [
            ['text', " \nint a;", 0],
        ]];
        yield 'string literal continued by a splice with blanks' => ["s = \"a\\ \nb\";\n#define X\n", [
            ['text', "s = \"ab\";\n", 0],
            ['#define', 'X', 13],
        ]];
        yield 'stray backslash before a splice of a directive' => ["#define B x\\\\\ny\nB\n", [
            ['#define', 'B x\\y', 0],
            ['text', "B\n", 16],
        ]];
        yield 'escape sequence split by a splice' => ["s = \"a\\\\\n\"\";\n#define X\n", [
            ['text', "s = \"a\\\"\";\n", 0],
            ['#define', 'X', 13],
        ]];
        yield 'hash after a line of a splice' => ["\\\n#define X 1\nX\n", [
            ['text', '', 0],
            ['#define', 'X 1', 2],
            ['text', "X\n", 14],
        ]];
        yield 'hash after blanks and a splice' => [" \\\n #define W\n", [['text', ' ', 0], ['#define', 'W', 4]]];
        yield 'backslash followed by text is not a splice' => ["#define X \\ a\nint b;", [
            ['#define', 'X \\ a', 0],
            ['text', 'int b;', 14],
        ]];
        yield 'backslash that is not a splice' =>["#define S \"\\n\"\n", [['#define', 'S "\\n"', 0]]];
        yield 'empty lines between directives' => ["#if 1\n\n\n#endif\n", [
            ['#if', '1', 0], ['text', "\n\n", 6], ['#endif', '', 8],
        ]];
        yield 'hash inside a block comment' => ["/*\n#define X\n*/\nint a;", [
            ['text', " \nint a;", 0],
        ]];
        yield 'directive after a comment on its line' => ["/* c */ #define X\n", [['#define', 'X', 8]]];
        yield 'comment with new-line after text' => ["int a; /*\n*/ #define X\n", [
            ['text', "int a;   #define X\n", 0],
        ]];
        yield 'directive after a line comment' => ["// a /* b\n#define X\n", [
            ['text', " \n", 0], ['#define', 'X', 10],
        ]];
        yield 'comment between the parts of a word' => ["a/**/b\n", [['text', "a b\n", 0]]];
        yield 'comment after a hash in text' => ["x # y /* c */ z\nint a;", [['text', "x # y   z\nint a;", 0]]];
        yield 'comment opening in a string literal' => ["s = \"/*\";\n#define X\n", [
            ['text', "s = \"/*\";\n", 0], ['#define', 'X', 10],
        ]];
        yield 'quote in a character constant' => ["c = '\"';\n#define X\n", [
            ['text', "c = '\"';\n", 0], ['#define', 'X', 9],
        ]];
        yield 'unterminated quote in text' => ["don't\n#define X\n", [
            ['text', "don't\n", 0], ['#define', 'X', 6],
        ]];
        yield 'digit separator in text' => ["x = 1'000;\n#define X\n", [
            ['text', "x = 1'000;\n", 0], ['#define', 'X', 11],
        ]];
        yield 'directive continued by a block comment' => ["#define X 1 /* a\nb */ 2\nint a;", [
            ['#define', 'X 1   2', 0], ['text', 'int a;', 24],
        ]];
        yield 'directive inside a comment of a directive' => ["#define X /*\n#undef X\n*/\n", [
            ['#define', 'X  ', 0],
        ]];
        yield 'digit separator before a comment' => ["x = 1'000; /*\n#define X\n*/\n", [
            ['text', "x = 1'000;  \n", 0],
        ]];
        yield 'quote after a number followed by a slash is a literal' => ["x = 1'/*';\n#define X\n", [
            ['text', "x = 1'/*';\n", 0], ['#define', 'X', 11],
        ]];
        yield 'prefixed character constant of a digit' => ["c = u8'0'; /*\n#define X\n*/\n", [
            ['text', "c = u8'0';  \n", 0],
        ]];
        yield 'number after an identifier and a dot' => ["x = abc.5'f; /*\n#define X\n*/\n", [
            ['text', "x = abc.5'f;  \n", 0],
        ]];
        yield 'number with an exponent sign' => ["x = 1e+5'0; /*\n#define X\n*/\n", [
            ['text', "x = 1e+5'0;  \n", 0],
        ]];
        yield 'identifier of a universal character name' => ["x = \\u00E9'a'; /*\n#define X\n*/\n", [
            ['text', "x = \\u00E9'a';  \n", 0],
        ]];
        yield 'digit separator in a directive' => ["#if 1'000 /* a\n*/ > X\nint a;", [
            ['#if', "1'000   > X", 0], ['text', 'int a;', 22],
        ]];
        yield 'long universal character name split by the blocks' => ["x = \\U0001F600'a'; /*\n#define X\n*/\n", [
            ['text', "x = \\U0001F600'a';  \n", 0],
        ]];
        yield 'line longer than a block' => [\str_repeat('int a; ', 12) . "\n#define X\n", [
            ['text', \str_repeat('int a; ', 12) . "\n", 0], ['#define', 'X', 85],
        ]];
        yield 'splice inside the end of a comment' => ["/* a *\\\n/\n#define X\n", [
            ['text', " \n", 0], ['#define', 'X', 10],
        ]];
        yield 'splices inside the end of a comment longer than the lookahead' => [
            "/* a *" . \str_repeat("\\\n", 10) . "/\n#define X\n",
            [['text', " \n", 0], ['#define', 'X', 28]],
        ];
        yield 'splice inside the start of a comment' => ["/\\\n*\n#define X\n*/\n", [
            ['text', " \n", 0],
        ]];
        yield 'splice inside the start of a line comment' => ["x /\\\n/ c /*\n#define X\n", [
            ['text', "x  \n", 0], ['#define', 'X', 12],
        ]];
        yield 'splice inside a digit separator' => ["x = 1\\\n'000; /*\n#define X\n*/\n", [
            ['text', "x = 1'000;  \n", 0],
        ]];
        yield 'splices inside a comment of a directive' => ["#define X 1 /\\\n* a \\\nb */ 2\nint a;", [
            ['#define', 'X 1   2', 0], ['text', 'int a;', 28],
        ]];
        yield 'splice inside the paste operator' => ["#\\\n# x\n#define X\n", [
            ['text', "## x\n", 0], ['#define', 'X', 7],
        ]];
        yield 'splice inside the digraph of hash' => ["%\\\n:define X\n", [['#define', 'X', 0]]];
        yield 'comment opening in a header name' => ["#include <a/*b.h>\n#define X\n*/\n", [
            ['#include', '<a/*b.h>', 0], ['#define', 'X', 18], ['text', "*/\n", 28],
        ]];
        yield 'comment before a header name' => ["#include /* c */ <a/*b.h>\n#define X\n", [
            ['#include', '<a/*b.h>', 0], ['#define', 'X', 26],
        ]];
        yield 'header name of include_next' => ["#include_next <a/*b.h>\n#define X\n", [
            ['#include_next', '<a/*b.h>', 0], ['#define', 'X', 23],
        ]];
        yield 'header name of import' => ["#import <a/*b.h>\n#define X\n", [
            ['#import', '<a/*b.h>', 0], ['#define', 'X', 17],
        ]];
        yield 'header name of embed' => ["#embed <a/*b>\n#define X\n", [
            ['#embed', '<a/*b>', 0], ['#define', 'X', 14],
        ]];
        yield 'header name in __has_include of if' => ["#if __has_include(<a/*b.h>)\n#define X\n#endif\n", [
            ['#if', '__has_include(<a/*b.h>)', 0], ['#define', 'X', 28], ['#endif', '', 38],
        ]];
        yield 'header name in __has_embed of elif' => ["#elif __has_embed ( <a/*b> )\n#define X\n", [
            ['#elif', '__has_embed ( <a/*b> )', 0], ['#define', 'X', 29],
        ]];
        yield 'comment opening in an angle of define' => ["#define H <a/*b.h>\n#define X\n*/\n", [
            ['#define', 'H <a ', 0],
        ]];
        yield 'comment opening in __has_include of define' => ["#define H __has_include(<a/*b.h>)\n#define X\n*/\n", [
            ['#define', 'H __has_include(<a ', 0],
        ]];
        yield 'quoted header name ends at its quote' => ["#include \"a\\\" /*\n#define X\n*/\n", [
            ['#include', "\"a\\\"  ", 0],
        ]];
        yield 'comments around the header name of __has_include' => ["#if __has_include(/*c*/ <a/*b.h>) /*d*/\n#define X\n", [
            ['#if', '__has_include(  <a/*b.h>)  ', 0], ['#define', 'X', 40],
        ]];
        yield 'directive name split by a splice before a header name' => ["#inc\\\nlude <a/*b.h>\n#define X\n", [
            ['#include', '<a/*b.h>', 0], ['#define', 'X', 20],
        ]];
        yield 'vertical tab after hash' => ["#\x0Bdefine X\n#\x0B\nint a;", [
            ['#define', 'X', 0], ['#', '', 11], ['text', 'int a;', 14],
        ]];
        yield 'universal character name split by a splice' => ["x = \\u00\\\nE9'a'; /*\n#define X\n*/\n", [
            ['text', "x = \\u00E9'a';  \n", 0],
        ]];
        yield 'blank line after a comment' => ["/* a */\n  #endif", [
            ['text', " \n", 0], ['#endif', '', 10],
        ]];
    }

    /**
     * @param list<list<int|string>> $expected
     */
    #[DataProvider('chunksDataProvider')]
    public function testFileIsSplitIntoTextAndDirectives(string $content, array $expected): void
    {
        foreach (self::BLOCK_SIZES as $blockSize) {
            $chunks = (new BlockReader($blockSize))->read(self::createFile($content));

            self::assertSame($expected, self::describe($chunks), \sprintf('Block of %d bytes', $blockSize));
        }
    }

    public function testLongTextIsReadByCompleteLines(): void
    {
        $content = \str_repeat("int a; /* x\ny */\n", 10) . "b \\\nc\n";
        $chunks = \iterator_to_array((new BlockReader(8))->read(self::createFile($content)), false);

        self::assertGreaterThan(1, \count($chunks));

        foreach ($chunks as $chunk) {
            self::assertInstanceOf(SourceChunk::class, $chunk);
            // Every chunk ends a line of source text, never in a comment or a splice
            self::assertStringNotContainsString('/*', (string) $chunk);
            self::assertStringEndsWith("\n", (string) $chunk);
            self::assertBytesPointToFile($chunk, $content);
        }
    }

    /**
     * @param list<list<int|string>> $expected
     */
    #[DataProvider('chunksDataProvider')]
    public function testEveryByteOfEveryChunkPointsToItsByteInTheFile(string $content, array $expected): void
    {
        foreach (self::BLOCK_SIZES as $blockSize) {
            $chunks = \iterator_to_array((new BlockReader($blockSize))->read(self::createFile($content)), false);

            self::assertCount(\count($expected), self::describe($chunks));

            foreach ($chunks as $chunk) {
                self::assertBytesPointToFile($chunk, $content);
            }
        }
    }

    public function testLongLineOfDirectiveIsReadWhole(): void
    {
        // More steps of the match than the default limit of the backtracking of PCRE allows
        $string = '"' . \str_repeat('\\n', 300_000) . '"';
        $content = '#define S ' . $string . " /* c */\nint a;\n";
        $directive = self::readSingleDirective($content);

        self::assertSame('S ' . $string . '  ', $directive->body);
    }

    public function testLimitOfTheBacktrackingIsRestored(): void
    {
        $limit = \ini_get('pcre.backtrack_limit');

        \iterator_to_array((new BlockReader())->read(self::createFile("#define X 1 /* c */\n")), false);

        self::assertSame($limit, \ini_get('pcre.backtrack_limit'));
    }

    /**
     * @return iterable<non-empty-string, array{string, list<list<int|string>>}>
     */
    public static function newLineDataProvider(): iterable
    {
        yield 'carriage return and line feed' => ["a /* c */ b\r\n#define X 1 // c\r\nc\r\n", [
            ['text', "a   b\r\n", 0], ['#define', 'X 1  ', 13], ['text', "c\r\n", 31],
        ]];
        yield 'carriage return' => ["a // c\r#define X 1 /* c */\rb\r", [
            ['text', "a  \r", 0], ['#define', 'X 1  ', 7], ['text', "b\r", 27],
        ]];
        yield 'block comment with carriage returns' => ["a /*\r\n*/ b\r\n#define X\r\n", [
            ['text', "a   b\r\n", 0], ['#define', 'X', 12],
        ]];
    }

    /**
     * @param list<list<int|string>> $expected
     */
    #[DataProvider('newLineDataProvider')]
    public function testCommentsAreReplacedWhateverTheNewLineIs(string $content, array $expected): void
    {
        foreach (self::BLOCK_SIZES as $blockSize) {
            $chunks = \iterator_to_array((new BlockReader($blockSize))->read(self::createFile($content)), false);

            self::assertSame($expected, self::describe($chunks), \sprintf('Block of %d bytes', $blockSize));

            foreach ($chunks as $chunk) {
                self::assertBytesPointToFile($chunk, $content);
            }
        }
    }

    public function testCommentIsReplacedByTheSpaceTakingItsPlaceInTheFile(): void
    {
        $content = "int /* a\nb */ x;\n#define X 1 /* c */ 2\n";
        $chunks = \iterator_to_array((new BlockReader())->read(self::createFile($content)), false);

        self::assertCount(2, $chunks);
        self::assertSame('int   x;' . "\n", (string) $chunks[0]);
        // "x" follows the comment of 10 bytes
        self::assertSame(\strpos($content, 'x'), $chunks[0]->getPhysicalOffset(6));
        self::assertSame(\strpos($content, '/*'), $chunks[0]->getPhysicalOffset(4));
        self::assertSame('#define X 1   2', (string) $chunks[1]);
        self::assertSame(\strpos($content, '2'), $chunks[1]->getPhysicalOffset(14));
    }

    public function testChunksAfterTheOnesWithCommentsStartWhereTheyDoInTheFile(): void
    {
        $content = \str_repeat("int a; /* x\ny */ int b;\n", 20);
        $chunks = \iterator_to_array((new BlockReader(16))->read(self::createFile($content)), false);

        $position = 0;

        foreach ($chunks as $chunk) {
            self::assertSame($position, $chunk->offset);

            $position = $chunk->getPhysicalOffset(\strlen($chunk->content));
        }

        self::assertSame(\strlen($content), $position);
    }

    /**
     * @return iterable<non-empty-string, array{string, int<0, max>}>
     */
    public static function unterminatedCommentDataProvider(): iterable
    {
        yield 'in text' => ['int a; /* b', 7];
        yield 'in text after a directive' => ["#define X\nint a; /* b", 17];
        yield 'in a directive' => ['#define X 1 /* c', 12];
        yield 'in a directive after a literal' => ['#define X "/*" /* c', 15];
        yield 'with a splice in its start' => ["x /\\\n* c", 2];
    }

    /**
     * @param int<0, max> $offset
     */
    #[DataProvider('unterminatedCommentDataProvider')]
    public function testUnterminatedCommentIsReportedWithItsPosition(string $content, int $offset): void
    {
        foreach (self::BLOCK_SIZES as $blockSize) {
            try {
                \iterator_to_array((new BlockReader($blockSize))->read(self::createFile($content)), false);
            } catch (SyntaxErrorException $e) {
                self::assertSame('Unterminated comment', $e->getMessage());
                self::assertSame($offset, $e->offset);

                continue;
            }

            self::fail(\sprintf('SyntaxErrorException has not been thrown with the block of %d bytes', $blockSize));
        }
    }

    public function testChunksKeepTheFile(): void
    {
        $file = self::createFile("int a;\n#define X\n");

        foreach ((new BlockReader())->read($file) as $chunk) {
            self::assertSame($file, $chunk->source);
        }
    }

    public function testDirectiveIsTheWholeLineWithoutSplices(): void
    {
        $directive = self::readSingleDirective("  #  define X \\\n 1\n");

        self::assertSame('#  define X  1', (string) $directive);
        self::assertSame('define', $directive->name);
        self::assertSame(10, $directive->bodyOffset);
    }

    public function testOffsetInDirectivePointsToItsByteInTheFile(): void
    {
        $content = "int a;\n#define X \\\n  1\n";
        $directive = self::readSingleDirective($content);

        // "1" is at offset 12 of "#define X   1" and at offset 21 of the file
        self::assertSame('1', $directive->content[12]);
        self::assertSame(21, $directive->getPhysicalOffset(12));
        self::assertSame('1', $content[21]);
    }

    public function testUnreadableFileIsReportedBeforeTheIteration(): void
    {
        $file = new FileSource(__DIR__ . '/missing-file.h');

        try {
            (new BlockReader())->read($file);
        } catch (InternalException $e) {
            self::assertStringContainsString('missing-file.h', $e->getMessage());
            self::assertInstanceOf(SourceExceptionInterface::class, $e->getPrevious());

            return;
        }

        self::fail('InternalException has not been thrown');
    }

    /**
     * Every byte of the chunk is the one of the file it points to, a space
     * that replaces a comment points to its start.
     */
    private static function assertBytesPointToFile(ChunkInterface $chunk, string $content): void
    {
        $text = (string) $chunk;

        for ($i = 0, $length = \strlen($text); $i < $length; ++$i) {
            $byte = $content[$chunk->getPhysicalOffset($i)] ?? '';

            self::assertTrue(
                $text[$i] === $byte || ($text[$i] === ' ' && $byte === '/'),
                \sprintf('The byte %d of %s points to %s', $i, \json_encode($text), \json_encode($byte)),
            );
        }
    }

    private static function readSingleDirective(string $content): SourceDirective
    {
        foreach ((new BlockReader())->read(self::createFile($content)) as $chunk) {
            if ($chunk instanceof SourceDirective) {
                return $chunk;
            }
        }

        self::fail('No directive has been read');
    }

    /**
     * Describes the chunks, the consecutive text chunks as one: a long text
     * may be put out by its lines.
     *
     * @param iterable<int, ChunkInterface> $chunks
     * @return list<list<int|string>>
     */
    private static function describe(iterable $chunks): array
    {
        $result = [];
        $previous = null;

        foreach ($chunks as $chunk) {
            if ($chunk instanceof SourceChunk && $previous instanceof SourceChunk) {
                $last = \array_pop($result);
                self::assertNotNull($last);
                self::assertSame($previous->getPhysicalOffset(\strlen($previous->content)), $chunk->offset);
                $result[] = ['text', $last[1] . $chunk, $last[2]];
                $previous = $chunk;

                continue;
            }

            $previous = $chunk;
            $result[] = match (true) {
                $chunk instanceof SourceDirective => ['#' . $chunk->name, $chunk->body, $chunk->offset],
                $chunk instanceof SourceChunk => ['text', (string) $chunk, $chunk->offset],
                default => self::fail(\sprintf('Unexpected chunk %s', $chunk::class)),
            };
        }

        return $result;
    }
}
