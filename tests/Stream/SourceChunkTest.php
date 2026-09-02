<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream;

use FFI\Preprocessor\Stream\Chunk\SourceMap;
use FFI\Preprocessor\Stream\SourceChunk;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SourceChunk::class)]
final class SourceChunkTest extends TestCase
{
    public function testSliceIsFragmentAtItsPlace(): void
    {
        // The chunk "in\⏎t x;" starts at offset 10 of its file
        $chunk = new SourceChunk('int x;', self::createFile(''), 10, SourceMap::createFromSplices([2], [2]));

        $slice = $chunk->slice(1, 4);

        self::assertSame('nt x', $slice->content);
        self::assertSame($chunk->source, $slice->source);
        self::assertSame(11, $slice->offset);
        self::assertSame(14, $slice->getPhysicalOffset(1));
        self::assertSame(6, $slice->getPhysicalLength(0, 4));
    }

    public function testSliceAfterSpliceStartsPastIt(): void
    {
        $chunk = new SourceChunk('int x;', self::createFile(''), 10, SourceMap::createFromSplices([2], [2]));

        $slice = $chunk->slice(2, 3);

        self::assertSame('t x', $slice->content);
        self::assertSame(14, $slice->offset);
        self::assertTrue($slice->splices->isEmpty());
    }

    public function testWholeSliceIsChunkItself(): void
    {
        $chunk = self::createChunk('int x;');

        self::assertSame($chunk, $chunk->slice(0, 6));
    }
}
