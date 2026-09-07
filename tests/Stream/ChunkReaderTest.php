<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream;

use FFI\Preprocessor\Stream\Exception\UnclosedCommentException;
use FFI\Preprocessor\Stream\ChunkReader;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceChunk;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;
use FFI\Preprocessor\Stream\Reader\BlockReader;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ChunkReader::class)]
final class ChunkReaderTest extends TestCase
{
    private const BLOCK_SIZES = [1, 2, 3, 5, 7, 16, 17, 64, BlockReader::DEFAULT_BLOCK_SIZE];

    /**
     * @param int<1, max> $blockSize
     * @return list<SourceChunk|SourceDirective>
     */
    private static function read(string $content, int $blockSize = BlockReader::DEFAULT_BLOCK_SIZE): array
    {
        return \iterator_to_array(ChunkReader::createDefault($blockSize)->read(self::createFile($content)), false);
    }

    public function testFileIsSplitIntoSourceTextAndDirectives(): void
    {
        $chunks = self::read("int a;\n#define X 1\nint b = X;\n");

        self::assertCount(3, $chunks);
        self::assertInstanceOf(SourceChunk::class, $chunks[0]);
        self::assertSame("int a;\n", $chunks[0]->content);
        self::assertInstanceOf(SourceDirective::class, $chunks[1]);
        self::assertSame('define', $chunks[1]->name);
        self::assertSame('X 1', $chunks[1]->body);
        self::assertSame(7, $chunks[1]->offset);
        self::assertInstanceOf(SourceChunk::class, $chunks[2]);
        self::assertSame("int b = X;\n", $chunks[2]->content);
        self::assertSame(19, $chunks[2]->offset);
    }

    public function testDirectiveStartedAfterCommentIsADirective(): void
    {
        $chunks = self::read("/* a\nb */ #define X 1 // c\n");

        self::assertCount(1, $chunks);
        self::assertInstanceOf(SourceDirective::class, $chunks[0]);
        self::assertSame('#define X 1  ', $chunks[0]->content);
        self::assertSame(10, $chunks[0]->offset);
    }

    public function testDirectiveInsideCommentIsText(): void
    {
        $chunks = self::read("/*\n#define X\n*/\nint a;\n");

        self::assertCount(1, $chunks);
        self::assertInstanceOf(SourceChunk::class, $chunks[0]);
        self::assertSame(" \nint a;\n", $chunks[0]->content);
    }

    public function testDirectiveContinuedBySpliceIsOneLine(): void
    {
        $chunks = self::read("#define F(a) \\\n  a + 1\nint b;\n");

        self::assertCount(2, $chunks);
        self::assertInstanceOf(SourceDirective::class, $chunks[0]);
        self::assertSame('#define F(a)   a + 1', $chunks[0]->content);
        self::assertSame(0, $chunks[0]->getPhysicalOffset(0));
        self::assertSame(17, $chunks[0]->getPhysicalOffset(15));
        self::assertSame(23, $chunks[1]->offset);
    }

    public function testBlockSizeDoesNotChangeTheChunks(): void
    {
        $content = "#include <a//b.h>\r\n/* c\n */ int a; // d\n  # /* e */ define X \\\n 1\n#if __has_include(<q/*r>)\n#endif\nx";
        $expected = self::describe(self::read($content));

        foreach (self::BLOCK_SIZES as $blockSize) {
            self::assertSame($expected, self::describe(self::read($content, $blockSize)), \sprintf('Block size %d', $blockSize));
        }
    }

    public function testUnterminatedCommentIsReported(): void
    {
        $this->expectException(UnclosedCommentException::class);

        self::read("#define X\n/* c");
    }

    /**
     * @param list<SourceChunk|SourceDirective> $chunks
     * @return list<string> the kind, the offset and the text of each chunk with the neighbouring text chunks joined
     */
    private static function describe(array $chunks): array
    {
        $result = [];
        $text = null;

        foreach ($chunks as $chunk) {
            if ($chunk instanceof SourceChunk) {
                if ($text === null) {
                    $text = [$chunk->offset, ''];
                }

                $text[1] .= $chunk->content;
                continue;
            }

            if ($text !== null) {
                $result[] = \sprintf('T@%d:%s', $text[0], $text[1]);
                $text = null;
            }

            $result[] = \sprintf('D@%d:%s', $chunk->offset, $chunk->content);
        }

        if ($text !== null) {
            $result[] = \sprintf('T@%d:%s', $text[0], $text[1]);
        }

        return $result;
    }
}
