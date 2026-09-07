<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream;

use FFI\Preprocessor\Stream\Chunk;
use FFI\Preprocessor\Stream\Chunk\SourceMap\Edit;
use FFI\Preprocessor\Stream\Chunk\SourceMap\SourceMap;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceChunk;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Chunk::class)]
final class ChunkTest extends TestCase
{
    public function testChunkIsItsText(): void
    {
        $chunk = new SourceChunk("int a;\n", self::createFile("int a;\n"), 0, SourceMap::createEmpty());

        self::assertSame("int a;\n", (string) $chunk);
    }

    public function testOffsetInTextIsTranslatedToOffsetInFile(): void
    {
        // The chunk "in\<new-line>t x;" starts at offset 10 of its file
        $map = SourceMap::createEmpty()->withEdits([new Edit(2, 2)]);
        $chunk = new SourceChunk('int x;', self::createFile(''), 10, $map);

        self::assertSame(10, $chunk->getPhysicalOffset(0));
        self::assertSame(14, $chunk->getPhysicalOffset(2));
        self::assertSame(5, $chunk->getPhysicalLength(0, 3));
    }
}
