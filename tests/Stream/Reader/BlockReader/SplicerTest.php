<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Reader\BlockReader;

use FFI\Preprocessor\Stream\Reader\BlockReader\Splicer;
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
        yield 'blanks before the new-line belong to the splice' => ["a \\ \t\f\x0B\nb", 'a b'];
        yield 'backslash followed by text is kept' => ["a \\ x\nb", "a \\ x\nb"];
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
        yield 'splices with blanks' => ["a\\  \nb\\\t\r\nc"];
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

    public function testMapOfCommentsIsEmptyWithoutThem(): void
    {
        self::assertTrue((new Splicer())->createSourceMap('int a;', [], [])->isEmpty());
    }

    /**
     * The text of the fragment and the comments replaced in it: `[offset after the space, bytes besides it]`.
     *
     * @return iterable<non-empty-string, array{non-empty-string, list<int<1, max>>, list<int<1, max>>}>
     */
    public static function commentedFragmentDataProvider(): iterable
    {
        yield 'comment only' => ['a b', [2], [4]];
        yield 'comments' => ['a b c', [2, 4], [6, 2]];
        yield 'comment and splice after it' => ["a b\\\nc", [2], [6]];
        yield 'comment and splice before it' => ["a\\\nb c", [5], [6]];
        yield 'comment and splice at the same offset' => ["a \\\nb", [2], [3]];
        yield 'comments between splices' => ["a\\\nb c d\\\ne", [5, 7], [3, 5]];
    }

    /**
     * @param non-empty-string $fragment the file with every comment shortened to the space
     * @param list<int<1, max>> $offsets
     * @param list<int<1, max>> $sizes
     */
    #[DataProvider('commentedFragmentDataProvider')]
    public function testCommentsAndSplicesAreMergedIntoOneMap(string $fragment, array $offsets, array $sizes): void
    {
        $splicer = new Splicer();
        $map = $splicer->createSourceMap($fragment, $offsets, $sizes);
        $text = $splicer->removeSplices($fragment);

        // The file the fragment is made of: the comment is `/*` and `*/` around the bytes it takes
        $file = '';
        $previous = 0;

        foreach ($offsets as $i => $commentOffset) {
            $file .= \substr($fragment, $previous, $commentOffset - 1 - $previous);
            $file .= '/' . \str_repeat('*', $sizes[$i]);
            $previous = $commentOffset;
        }

        $file .= \substr($fragment, $previous);

        for ($i = 0, $length = \strlen($text); $i < $length; ++$i) {
            $physical = $map->getPhysicalOffset($i);

            if ($text[$i] === ' ' && ($file[$physical] ?? '') === '/') {
                continue;
            }

            self::assertSame($text[$i], $file[$physical] ?? '', \sprintf('The byte at offset %d of the text', $i));
        }

        self::assertSame(\strlen($file), $map->getPhysicalOffset($length));
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, int<0, max>, bool}>
     */
    public static function spliceEndDataProvider(): iterable
    {
        yield 'line feed after a backslash' => ["a\\\n", 2, true];
        yield 'line feed of carriage return and line feed' => ["a\\\r\n", 3, true];
        yield 'carriage return after a backslash' => ["a\\\r\n", 2, true];
        yield 'blanks between' => ["a\\ \t\n", 4, true];
        yield 'line feed after text' => ["a\n", 1, false];
        yield 'line feed at the start' => ["\n", 0, false];
        yield 'text between' => ["\\a\n", 2, false];
    }

    /**
     * @param int<0, max> $offset
     */
    #[DataProvider('spliceEndDataProvider')]
    public function testNewLineEndsSpliceAfterBackslash(string $text, int $offset, bool $expected): void
    {
        self::assertSame($expected, (new Splicer())->isSpliceEnd($text, $offset));
    }
}
