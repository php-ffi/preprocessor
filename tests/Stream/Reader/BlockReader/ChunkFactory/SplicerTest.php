<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Reader\BlockReader\ChunkFactory;

use FFI\Preprocessor\Stream\Reader\BlockReader\ChunkFactory\Splicer;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Splicer::class)]
final class SplicerTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function splicingDataProvider(): iterable
    {
        yield 'empty fragment' => ['', ''];
        yield 'no backslash' => ["int a;\nint b;\n", "int a;\nint b;\n"];
        yield 'backslash before line feed' => ["a \\\nb", 'a b'];
        yield 'backslash before carriage return and line feed' => ["a \\\r\nb", 'a b'];
        yield 'backslash before lone carriage return' => ["a \\\rb", 'a b'];
        yield 'consecutive splices' => ["a\\\n\\\r\n\\\rb", 'ab'];
        yield 'splice inside identifier' => ["in\\\nt x;", 'int x;'];
        yield 'splice at the end' => ["#define X 1 \\\n", '#define X 1 '];
        yield 'splice only' => ["\\\n", ''];
        yield 'backslash followed by space is kept' => ["a \\ \nb", "a \\ \nb"];
        yield 'escape sequence is kept' => ['"a\\nb"', '"a\\nb"'];
        yield 'backslash at the end without new-line is kept' => ['a\\', 'a\\'];
        yield 'only the backslash next to the new-line is deleted' => ["a\\\\\nb", 'a\\b'];
        yield 'line feed after carriage return belongs to the splice' => ["a\\\r\n\nb", "a\nb"];
    }

    #[DataProvider('splicingDataProvider')]
    public function testBackslashFollowedByNewLineIsDeleted(string $fragment, string $expected): void
    {
        self::assertSame($expected, (new Splicer())->removeSplices($fragment));
    }

    #[DataProvider('splicingDataProvider')]
    public function testMapIsEmptyExactlyWhenNothingIsDeleted(string $fragment, string $expected): void
    {
        $map = (new Splicer())->createSourceMap($fragment);

        self::assertSame($fragment === $expected, $map->isEmpty());
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function splicedFragmentDataProvider(): iterable
    {
        yield 'splice in the middle' => ["#define X \\\n  1\n"];
        yield 'splices of every new-line' => ["a\\\nb\\\r\nc\\\rd"];
        yield 'consecutive splices' => ["x\\\n\\\n\\\ny"];
        yield 'splice at the start' => ["\\\nint x;"];
        yield 'splices in an identifier' => ["lo\\\nng\\\r\n_name = 1;"];
        yield 'escaped backslash before a splice' => ["\"\\\\\" \\\n x"];
    }

    #[DataProvider('splicedFragmentDataProvider')]
    public function testEveryByteOfTheTextPointsToItsByteInTheFragment(string $fragment): void
    {
        $splicer = new Splicer();
        $text = $splicer->removeSplices($fragment);
        $map = $splicer->createSourceMap($fragment);

        for ($offset = 0, $length = \strlen($text); $offset < $length; ++$offset) {
            self::assertSame(
                $text[$offset],
                $fragment[$map->getPhysicalOffset($offset)],
                \sprintf('The byte at offset %d of the text', $offset),
            );
        }
    }
}
