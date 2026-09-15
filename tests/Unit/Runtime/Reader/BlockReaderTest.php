<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Runtime\Reader;

use FFI\Preprocessor\Runtime\Exception\UnclosedCommentException;
use FFI\Preprocessor\Runtime\Reader\BlockReader;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Source\FileSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(BlockReader::class)]
final class BlockReaderTest extends TestCase
{
    /**
     * Sizes of the blocks, the small ones split every construction by the end of a block somewhere.
     *
     * @return iterable<non-empty-string, array{int<1, max>}>
     */
    public static function blockSizeDataProvider(): iterable
    {
        foreach ([1, 2, 3, 5, 17, BlockReader::DEFAULT_BLOCK_SIZE] as $size) {
            yield 'block of ' . $size . ' bytes' => [$size];
        }
    }

    /**
     * @param int<1, max> $blockSize
     */
    #[DataProvider('blockSizeDataProvider')]
    public function testSplicesAndCommentsAreRemovedWhateverTheBlockSizeIs(int $blockSize): void
    {
        $file = self::createFile("int a; /* c */ int \\\nb;\n");

        $text = '';

        foreach (BlockReader::createDefault($blockSize)->read($file) as $chunk) {
            $text .= $chunk->content;
        }

        self::assertSame("int a;   int b;\n", $text);
    }

    /**
     * @param int<1, max> $blockSize
     */
    #[DataProvider('blockSizeDataProvider')]
    public function testEveryByteOfEveryChunkPointsToItsByteInTheFile(int $blockSize): void
    {
        $content = "ab\\\ncd\nef\\\n\\\ngh\n";
        $file = self::createFile($content);

        foreach (BlockReader::createDefault($blockSize)->read($file) as $chunk) {
            foreach (\str_split($chunk->content) as $i => $byte) {
                self::assertSame($byte, $content[$chunk->getPhysicalOffset($i)]);
            }
        }
    }

    public function testEmptyFileHasNoChunks(): void
    {
        self::assertSame([], [...BlockReader::createDefault()->read(self::createFile(''))]);
    }

    /**
     * @param int<1, max> $blockSize
     */
    #[DataProvider('blockSizeDataProvider')]
    public function testByteOrderMarkIsNotAPartOfTheText(int $blockSize): void
    {
        $file = self::createFile("\xEF\xBB\xBFint x;\n");

        $chunks = [...BlockReader::createDefault($blockSize)->read($file)];

        self::assertSame('int x;', \substr(\implode('', $chunks), 0, 6));
        self::assertSame(3, $chunks[0]->getPhysicalOffset(0));
    }

    public function testFileOfByteOrderMarkOnlyHasNoChunks(): void
    {
        $file = self::createFile("\xEF\xBB\xBF");

        self::assertSame([], [...BlockReader::createDefault(1)->read($file)]);
    }

    /**
     * @param int<1, max> $blockSize
     */
    #[DataProvider('blockSizeDataProvider')]
    public function testUnterminatedCommentIsReportedWithItsPlace(int $blockSize): void
    {
        $file = self::createFile('int x; /* c');

        try {
            \iterator_to_array(BlockReader::createDefault($blockSize)->read($file), false);
            self::fail('The exception is expected');
        } catch (UnclosedCommentException $e) {
            self::assertSame($file, $e->source);
            self::assertSame(7, $e->offset);
        }
    }

    public function testTransformersAreOnesGivenToTheReader(): void
    {
        $file = self::createFile("a /* c */\\\nb\n");

        self::assertSame("a /* c */\\\nb\n",\implode('', [...(new BlockReader([], 4))->read($file)]));
    }

    public function testFailureOfTheFileIsNotWrapped(): void
    {
        $file = new FileSource(__DIR__ . '/missing-file.h');

        try {
            \iterator_to_array(BlockReader::createDefault()->read($file), false);
            self::fail('The exception is expected');
        } catch (SourceExceptionInterface $e) {
            self::assertStringContainsString('missing-file.h', $e->getMessage());
        }
    }
}
