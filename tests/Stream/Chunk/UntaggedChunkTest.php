<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Chunk;

use FFI\Preprocessor\Stream\Chunk\SourceMap\Edit;
use FFI\Preprocessor\Stream\Chunk\SourceMap\SourceMap;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(UntaggedChunk::class)]
final class UntaggedChunkTest extends TestCase
{
    public function testEditReplacesTheTextAndKeepsThePlaceInTheFile(): void
    {
        // "a/* c */b" at offset 10 of the file
        $chunk = self::createUntaggedChunk('a/* c */b', 10)->edit([new Edit(1, 7, ' ')]);

        self::assertSame('a b', $chunk->content);
        self::assertSame(10, $chunk->offset);
        self::assertSame(10, $chunk->getPhysicalOffset(0));
        self::assertSame(11, $chunk->getPhysicalOffset(1));
        self::assertSame(18, $chunk->getPhysicalOffset(2));
    }

    public function testEditWithoutEditsIsTheChunkItself(): void
    {
        $chunk = self::createUntaggedChunk('int a;');

        self::assertSame($chunk, $chunk->edit([]));
    }

    public function testSliceStartsAtItsPlaceInTheFile(): void
    {
        // "ab\<new-line>cd" read as "abcd"
        $chunk = self::createUntaggedChunk("ab\\\ncd", 5)->edit([new Edit(2, 2)]);

        $slice = $chunk->slice(2);

        self::assertSame('cd', $slice->content);
        self::assertSame(9, $slice->offset);
        self::assertTrue($slice->map->isEmpty());
        self::assertSame('b', $chunk->slice(1, 1)->content);
    }

    public function testConcatKeepsThePlaceOfBothInTheFile(): void
    {
        // "ab\<new-line>c" and "d/**/e" are the consecutive blocks of a file
        $first = self::createUntaggedChunk("ab\\\nc", 0)->edit([new Edit(2, 2)]);
        $second = self::createUntaggedChunk('d/**/e', 5)->edit([new Edit(1, 4, ' ')]);

        $chunk = $first->concat($second);

        self::assertSame('abcd e', $chunk->content);
        self::assertSame(0, $chunk->getPhysicalOffset(0));
        self::assertSame(4, $chunk->getPhysicalOffset(2));
        self::assertSame(5, $chunk->getPhysicalOffset(3));
        self::assertSame(6, $chunk->getPhysicalOffset(4));
        self::assertSame(10, $chunk->getPhysicalOffset(5));
    }

    public function testConcatWithEmptyChunkIsTheOtherOne(): void
    {
        $chunk = self::createUntaggedChunk('int a;');
        $empty = self::createUntaggedChunk('');

        self::assertSame($chunk, $empty->concat($chunk));
        self::assertSame($chunk, $chunk->concat($empty));
    }

    #[DataProvider('lineEndDataProvider')]
    public function testLastLineEndIsFoundOrLeftToTheContinuation(string $text, ?int $expected): void
    {
        self::assertSame($expected, self::createUntaggedChunk($text)->findLastLineEnd());
    }

    /**
     * @return iterable<non-empty-string, array{string, int|null}>
     */
    public static function lineEndDataProvider(): iterable
    {
        yield 'empty' => ['', null];
        yield 'no new-line' => ['int a;', null];
        yield 'line feed' => ["int a;\nint b", 7];
        yield 'text ends in line feed' => ["int a;\n", 7];
        yield 'carriage return and line feed' => ["a\r\nb", 3];
        yield 'text ends in carriage return' => ["a\r", null];
        yield 'text ends in carriage return after a line' => ["a\nb\r", 2];
        yield 'lone carriage returns' => ["a\rb\rc", 4];
        yield 'new-line at the start' => ["\nabc", 1];
    }

    #[DataProvider('lineStartDataProvider')]
    public function testLineStartIsTheOffsetAfterThePreviousNewLine(string $text, int $offset, int $expected): void
    {
        self::assertSame($expected, self::createUntaggedChunk($text)->getLineStart($offset));
    }

    /**
     * @return iterable<non-empty-string, array{string, int<0, max>, int}>
     */
    public static function lineStartDataProvider(): iterable
    {
        yield 'first line' => ["abc\ndef", 2, 0];
        yield 'second line' => ["abc\ndef", 5, 4];
        yield 'right after the new-line' => ["abc\ndef", 4, 4];
        yield 'carriage return and line feed' => ["a\r\nbc", 4, 3];
        yield 'carriage return' => ["a\rbc", 3, 2];
        yield 'at the start' => ['abc', 0, 0];
    }

    public function testSliceInsideSpliceKeepsTheSkippedBytesInsideIt(): void
    {
        // The chunk "in\<new-line>t x;" starts at offset 10 of its file
        $chunk = new UntaggedChunk('int x;', self::createFile(''), 10, SourceMap::createEmpty()->withEdits([new Edit(2, 2)]));

        $slice = $chunk->slice(1, 4);

        self::assertSame('nt x', $slice->content);
        self::assertSame($chunk->source, $slice->source);
        self::assertSame(11, $slice->offset);
        self::assertSame(14, $slice->getPhysicalOffset(1));
        self::assertSame(6, $slice->getPhysicalLength(0, 4));
    }

    public function testSliceAfterSpliceStartsPastIt(): void
    {
        $chunk = new UntaggedChunk('int x;', self::createFile(''), 10, SourceMap::createEmpty()->withEdits([new Edit(2, 2)]));

        $slice = $chunk->slice(2, 3);

        self::assertSame('t x', $slice->content);
        self::assertSame(14, $slice->offset);
        self::assertSame(15, $slice->getPhysicalOffset(1));
    }

    public function testWholeSliceIsChunkItself(): void
    {
        $chunk = self::createUntaggedChunk('int x;');

        self::assertSame($chunk, $chunk->slice(0, 6));
    }
}
