<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Reader\Transformer;

use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;
use FFI\Preprocessor\Stream\Reader\Transformer\SpliceRemover;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SpliceRemover::class)]
final class SpliceRemoverTest extends TestCase
{
    /**
     * @param non-empty-string $text
     */
    #[DataProvider('splicedTextDataProvider')]
    public function testSplicesAreRemoved(string $text, string $expected): void
    {
        $transformation = (new SpliceRemover())->transform(
            self::createUntaggedChunk($text),
            true,
        );

        self::assertSame($expected, $transformation->ready->content);
        self::assertTrue($transformation->rest->isEmpty());
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, string}>
     */
    public static function splicedTextDataProvider(): iterable
    {
        yield 'no splice' => ['int a;', 'int a;'];
        yield 'line feed' => ["in\\\nt a;", 'int a;'];
        yield 'carriage return and line feed' => ["in\\\r\nt a;", 'int a;'];
        yield 'carriage return' => ["in\\\rt a;", 'int a;'];
        yield 'blanks before the new-line' => ["in\\ \t\nt a;", 'int a;'];
        yield 'two splices in a row' => ["i\\\n\\\nnt", 'int'];
        yield 'splice at the end' => ["int\\\n", 'int'];
        yield 'backslash without new-line' => ['a\\b', 'a\\b'];
        yield 'backslash at the end of the file' => ['int\\', 'int\\'];
        yield 'escaped backslash before the splice' => ["a\\\\\nb", "a\\b"];
        yield 'new-line without backslash' => ["a\nb", "a\nb"];
    }

    public function testSpliceWhichNewLineIsInTheNextBlockWaitsForIt(): void
    {
        $transformation = (new SpliceRemover())->transform(
            self::createUntaggedChunk("ab\\ \r"),
            false,
        );

        self::assertSame('ab', $transformation->ready->content);
        self::assertSame("\\ \r", $transformation->rest->content);
        self::assertSame(2, $transformation->rest->offset);
    }

    public function testBackslashFollowedByTextDoesNotWait(): void
    {
        $transformation = (new SpliceRemover())->transform(
            self::createUntaggedChunk('a\\b'),
            false,
        );

        self::assertSame('a\\b', $transformation->ready->content);
        self::assertTrue($transformation->rest->isEmpty());
    }

    public function testEveryByteOfTheResultPointsToItsByteOfTheFile(): void
    {
        $file = "in\\\nt x\\ \n= 1;";
        $result = (new SpliceRemover())->transform(self::createUntaggedChunk($file, 7), true)->ready;

        self::assertSame('int x= 1;', $result->content);

        foreach (\str_split($result->content) as $offset => $byte) {
            self::assertSame($byte, $file[$result->getPhysicalOffset($offset) - 7]);
        }
    }
}
