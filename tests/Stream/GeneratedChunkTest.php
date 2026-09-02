<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream;

use FFI\Preprocessor\Stream\GeneratedChunk;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GeneratedChunk::class)]
final class GeneratedChunkTest extends TestCase
{
    public function testEveryOffsetMapsToPlaceOfReplacement(): void
    {
        $chunk = new GeneratedChunk('((1) > (2))', self::createFile('MAX(1, 2)'), 7);

        self::assertSame('((1) > (2))', (string) $chunk);
        self::assertSame(7, $chunk->offset);
        self::assertSame(7, $chunk->getPhysicalOffset(0));
        self::assertSame(7, $chunk->getPhysicalOffset(5));
        self::assertSame(1, $chunk->getPhysicalLength(2, 4));
    }
}
