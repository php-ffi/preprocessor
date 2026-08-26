<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Chunk;

use FFI\Preprocessor\Stream\Chunk\SourceMap;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SourceMap::class)]
final class SourceMapTest extends TestCase
{
    public function testEmptyMapKeepsEveryOffsetAndSize(): void
    {
        $map = SourceMap::createEmpty();

        self::assertTrue($map->isEmpty());
        self::assertSame(0, $map->getPhysicalOffset(0));
        self::assertSame(42, $map->getPhysicalOffset(42));
        self::assertSame(3, $map->getPhysicalLength(10, 3));
    }

    public function testMapWithoutSplicesIsEmpty(): void
    {
        self::assertTrue(SourceMap::createFromSplices([], [])->isEmpty());
    }

    /**
     * Splices of the text `ab\⏎cd\⏎\⏎ef` (offsets 2, 6 and 8 in the file,
     * offsets 2, 4 and 4 in the text `abcdef`).
     *
     * @return iterable<non-empty-string, array{int<0, max>, int<0, max>}>
     */
    public static function offsetDataProvider(): iterable
    {
        yield 'before the first splice' => [0, 0];
        yield 'last byte before the first splice' => [1, 1];
        yield 'right after the first splice' => [2, 4];
        yield 'between the splices' => [3, 5];
        yield 'right after consecutive splices' => [4, 10];
        yield 'after every splice' => [5, 11];
        yield 'end of the text' => [6, 12];
    }

    /**
     * @param int<0, max> $offset
     * @param int<0, max> $expected
     */
    #[DataProvider('offsetDataProvider')]
    public function testOffsetIsShiftedByTheSplicesBeforeIt(int $offset, int $expected): void
    {
        $map = SourceMap::createFromSplices([2, 6, 8], [2, 2, 2]);

        self::assertFalse($map->isEmpty());
        self::assertSame($expected, $map->getPhysicalOffset($offset));
    }

    public function testSizeOfSingleByteNeverIncludesSplices(): void
    {
        $map = SourceMap::createFromSplices([2], [2]);

        self::assertSame(1, $map->getPhysicalLength(1, 1));
        self::assertSame(1, $map->getPhysicalLength(2, 1));
    }

    public function testSizeOfFragmentAcrossSplicesIncludesThem(): void
    {
        $map = SourceMap::createFromSplices([2, 6, 8], [2, 2, 2]);

        self::assertSame(12, $map->getPhysicalLength(0, 6));
        self::assertSame(4, $map->getPhysicalLength(1, 2));
    }

    public function testCarriageReturnAndLineFeedSpliceIsThreeBytes(): void
    {
        $map = SourceMap::createFromSplices([1], [3]);

        self::assertSame(4, $map->getPhysicalOffset(1));
        self::assertSame(5, $map->getPhysicalLength(0, 2));
    }
}
