<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Chunk\SourceMap;

use FFI\Preprocessor\Stream\Chunk\SourceMap\Edit;
use FFI\Preprocessor\Stream\Chunk\SourceMap\SourceMap;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SourceMap::class)]
final class SourceMapTest extends TestCase
{
    public function testEmptyMapKeepsEveryOffset(): void
    {
        $map = SourceMap::createEmpty();

        self::assertTrue($map->isEmpty());
        self::assertSame(0, $map->getPhysicalOffset(0));
        self::assertSame(42, $map->getPhysicalOffset(42));
        self::assertSame(3, $map->getPhysicalLength(5, 3));
    }

    public function testRemovedBytesAreSkippedFromTheirOffsetOn(): void
    {
        // "ab\<new-line>cd" read as "abcd"
        $map = SourceMap::createEmpty()->withEdits([new Edit(2, 2)]);

        self::assertFalse($map->isEmpty());
        self::assertSame(0, $map->getPhysicalOffset(0));
        self::assertSame(1, $map->getPhysicalOffset(1));
        self::assertSame(4, $map->getPhysicalOffset(2));
        self::assertSame(5, $map->getPhysicalOffset(3));
    }

    public function testReplacementTakesThePlaceOfWhatItReplaces(): void
    {
        // "a/* c */b" read as "a b"
        $map = SourceMap::createEmpty()->withEdits([new Edit(1, 7, ' ')]);

        self::assertSame(1, $map->getPhysicalOffset(1));
        self::assertSame(8, $map->getPhysicalOffset(2));
    }

    public function testEditsAreAppliedToTheMapOfEditedText(): void
    {
        // "a\<new-line>b/* c */d" read as "ab d" in two steps
        $spliced = SourceMap::createEmpty()->withEdits([new Edit(1, 2)]);
        $replaced = $spliced->withEdits([new Edit(2, 7, ' ')]);

        self::assertSame(0, $replaced->getPhysicalOffset(0));
        self::assertSame(3, $replaced->getPhysicalOffset(1));
        self::assertSame(4, $replaced->getPhysicalOffset(2));
        self::assertSame(11, $replaced->getPhysicalOffset(3));
    }

    public function testSkippedBytesInsideReplacedRangeAreCounted(): void
    {
        // "/\<new-line>* c *\<new-line>/x" read as "/* c */x", then the comment becomes a space
        $spliced = SourceMap::createEmpty()->withEdits([new Edit(1, 2), new Edit(8, 2)]);
        $replaced = $spliced->withEdits([new Edit(0, 7, ' ')]);

        self::assertSame(0, $replaced->getPhysicalOffset(0));
        self::assertSame(11, $replaced->getPhysicalOffset(1));
    }

    public function testAdjacentEditsAreBothApplied(): void
    {
        // "/*a*//*b*/x" read as "  x"
        $map = SourceMap::createEmpty()->withEdits([new Edit(0, 5, ' '), new Edit(5, 5, ' ')]);

        self::assertSame(0, $map->getPhysicalOffset(0));
        self::assertSame(5, $map->getPhysicalOffset(1));
        self::assertSame(10, $map->getPhysicalOffset(2));
    }

    public function testSliceStartsAtItsOwnOffsets(): void
    {
        // "ab\<new-line>cd\<new-line>ef" read as "abcdef"
        $map = SourceMap::createEmpty()->withEdits([new Edit(2, 2), new Edit(6, 2)]);

        $slice = $map->slice(3, 3);

        // The slice "def" starts at offset 5 of the file: the first splice is before it
        self::assertSame(0, $slice->getPhysicalOffset(0));
        self::assertSame(3, $slice->getPhysicalOffset(1));
        self::assertSame(4, $slice->getPhysicalOffset(2));
    }

    public function testSliceWithoutSkippedBytesIsEmpty(): void
    {
        $map = SourceMap::createEmpty()->withEdits([new Edit(2, 2)]);

        self::assertTrue($map->slice(0, 2)->isEmpty());
        self::assertTrue($map->slice(2, 3)->isEmpty());
    }

    public function testPhysicalLengthIncludesTheSplicesInside(): void
    {
        $map = SourceMap::createEmpty()->withEdits([new Edit(2, 2)]);

        self::assertSame(2, $map->getPhysicalLength(0, 2));
        self::assertSame(5, $map->getPhysicalLength(0, 3));
        self::assertSame(2, $map->getPhysicalLength(2, 2));
    }

    public function testConcatKeepsTheSkippedBytesOfBothAndTheGapBetweenThem(): void
    {
        $first = SourceMap::createEmpty()->withEdits([new Edit(1, 2)]);
        $second = SourceMap::createEmpty()->withEdits([new Edit(1, 3)]);

        // The first text "abc" takes 5 bytes of the file, the second one starts 4 bytes after it
        $map = $first->concat($second, 3, 4);

        self::assertSame(0, $map->getPhysicalOffset(0));
        self::assertSame(3, $map->getPhysicalOffset(1));
        self::assertSame(4, $map->getPhysicalOffset(2));
        self::assertSame(9, $map->getPhysicalOffset(3));
        self::assertSame(13, $map->getPhysicalOffset(4));
    }

    public function testConcatOfMapsWithoutSkippedBytesStaysEmpty(): void
    {
        $map = SourceMap::createEmpty()->concat(SourceMap::createEmpty(), 5, 0);

        self::assertTrue($map->isEmpty());
    }

    /**
     * @param list<Edit> $edits
     * @param list<int<0, max>> $expected the offset in the file of each byte of the edited text
     */
    #[DataProvider('editedTextDataProvider')]
    public function testEveryByteOfEditedTextPointsToItsByteOfFile(string $file, array $edits, array $expected): void
    {
        $map = SourceMap::createEmpty()->withEdits($edits);

        foreach ($expected as $offset => $physical) {
            self::assertSame($physical, $map->getPhysicalOffset($offset), \sprintf('Offset %d of %s', $offset, \json_encode($file)));
        }
    }

    /**
     * @return iterable<non-empty-string, array{string, list<Edit>, list<int<0, max>>}>
     */
    public static function editedTextDataProvider(): iterable
    {
        yield 'splice in the middle' => ["ab\\\ncd", [new Edit(2, 2)], [0, 1, 4, 5]];
        yield 'two splices' => ["a\\\nb\\\nc", [new Edit(1, 2), new Edit(4, 2)], [0, 3, 6]];
        yield 'splice at the start' => ["\\\nab", [new Edit(0, 2)], [2, 3]];
        yield 'comment between words' => ['a/**/b', [new Edit(1, 4, ' ')], [0, 1, 5]];
        yield 'comment and splice' => ["a/**/b\\\nc", [new Edit(1, 4, ' '), new Edit(6, 2)], [0, 1, 5, 8]];
    }
}
