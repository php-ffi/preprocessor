<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Segmenter;

use FFI\Preprocessor\Stream\Chunk\SourceMap\Edit;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceChunk;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;
use FFI\Preprocessor\Stream\Segmenter\LineSegmenter;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(LineSegmenter::class)]
final class LineSegmenterTest extends TestCase
{
    /**
     * @param iterable<int, UntaggedChunk> $chunks
     * @return list<string> the kind and the text of each chunk: "T" for the source text, "D" for the directive
     */
    private static function segment(iterable $chunks): array
    {
        $result = [];

        foreach ((new LineSegmenter())->segment($chunks) as $chunk) {
            $kind = 'T';

            if ($chunk instanceof SourceDirective) {
                $kind = 'D';
            }

            $result[] = $kind . ':' . $chunk->content;
        }

        return $result;
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('textDataProvider')]
    public function testLinesAreSplitIntoSourceTextAndDirectives(string $text, array $expected): void
    {
        self::assertSame($expected, self::segment([self::createUntaggedChunk($text)]));
    }

    /**
     * @return iterable<non-empty-string, array{string, list<string>}>
     */
    public static function textDataProvider(): iterable
    {
        yield 'empty' => ['', []];
        yield 'text only' => ["int a;\nint b;\n", ["T:int a;\nint b;\n"]];
        yield 'directive only' => ['#define X 1', ['D:#define X 1']];
        yield 'directive and its new-line' => ["#define X 1\n", ['D:#define X 1']];
        yield 'text around a directive' => ["a\n#define X\nb\n", ["T:a\n", 'D:#define X', "T:b\n"]];
        yield 'two directives in a row' => ["#if X\n#endif\n", ['D:#if X', 'D:#endif']];
        yield 'blanks before the marker' => ["a\n \t #define X\n", ["T:a\n", 'D:#define X']];
        yield 'hash in the middle of a line' => ["a # b\n", ["T:a # b\n"]];
        yield 'double hash is not a directive' => ["## a\n", ["T:## a\n"]];
        yield 'digraph' => ["%: define X\n", ['D:%: define X']];
        yield 'double digraph is not a directive' => ["%:%: a\n", ["T:%:%: a\n"]];
        yield 'null directive' => ["#\n", ['D:#']];
        yield 'carriage return and line feed' => ["a\r\n#define X\r\nb\r\n", ["T:a\r\n", 'D:#define X', "T:b\r\n"]];
        yield 'carriage return' => ["a\r#define X\rb\r", ["T:a\r", 'D:#define X', "T:b\r"]];
        yield 'text ends without new-line' => ["a\nb", ["T:a\n", 'T:b']];
    }

    /**
     * @param non-empty-string $line
     */
    #[DataProvider('directiveDataProvider')]
    public function testDirectiveHasItsNameAndBody(string $line, string $name, string $body): void
    {
        $chunks = \iterator_to_array(
            (new LineSegmenter())->segment([self::createUntaggedChunk($line)]),
            false,
        );

        self::assertCount(1, $chunks);
        self::assertInstanceOf(SourceDirective::class, $chunks[0]);
        self::assertSame($name, $chunks[0]->name);
        self::assertSame($body, $chunks[0]->body);
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, string, string}>
     */
    public static function directiveDataProvider(): iterable
    {
        yield 'define' => ['#define X 1', 'define', 'X 1'];
        yield 'blanks around the name' => ['#  include   <a.h>', 'include', '<a.h>'];
        yield 'name without body' => ['#endif', 'endif', ''];
        yield 'null directive' => ['#', '', ''];
        yield 'number instead of the name' => ['# 42 "a.h"', '', '42 "a.h"'];
        yield 'name glued to the body' => ['#if(X)', 'if', '(X)'];
        yield 'digraph' => ['%: define X', 'define', 'X'];
    }

    public function testChunksKeepTheirPlaceInTheFile(): void
    {
        // "a\<new-line>b\n  #define X\nc" read as "ab\n  #define X\nc"
        $file = "a\\\nb\n  #define X\nc";
        $chunk = self::createUntaggedChunk($file, 10)->edit([new Edit(1, 2)]);

        $chunks = \iterator_to_array((new LineSegmenter())->segment([$chunk]), false);

        self::assertCount(3, $chunks);
        self::assertInstanceOf(SourceChunk::class, $chunks[0]);
        self::assertSame("ab\n", $chunks[0]->content);
        self::assertSame(10, $chunks[0]->offset);
        self::assertSame(13, $chunks[0]->getPhysicalOffset(1));
        self::assertSame(14, $chunks[0]->getPhysicalOffset(2));
        self::assertSame('#define X', $chunks[1]->content);
        self::assertSame(17, $chunks[1]->offset);
        self::assertSame('c', $chunks[2]->content);
        self::assertSame(27, $chunks[2]->offset);
    }

    public function testLineSplitBetweenChunksIsJoined(): void
    {
        $chunks = [
            self::createUntaggedChunk("a\n#def", 0),
            self::createUntaggedChunk("ine X 1\nb", 6),
        ];

        self::assertSame(["T:a\n", 'D:#define X 1', 'T:b'], self::segment($chunks));
    }

    public function testCarriageReturnAtTheEndOfChunkWaitsForLineFeed(): void
    {
        $chunks = [
            self::createUntaggedChunk("#define X\r", 0),
            self::createUntaggedChunk("\nb\n", 10),
        ];

        self::assertSame(['D:#define X', "T:b\n"], self::segment($chunks));
    }
}
