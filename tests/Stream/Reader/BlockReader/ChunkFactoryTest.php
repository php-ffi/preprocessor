<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Reader\BlockReader;

use FFI\Preprocessor\Stream\Reader\BlockReader\ChunkFactory;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ChunkFactory::class)]
final class ChunkFactoryTest extends TestCase
{
    /**
     * The expected values are `[name, body, bodyOffset]`.
     *
     * @return iterable<non-empty-string, array{non-empty-string, array{string, string, int}}>
     */
    public static function directiveDataProvider(): iterable
    {
        yield 'name and body' => ['#define X 1', ['define', 'X 1', 8]];
        yield 'name only' => ['#endif', ['endif', '', 6]];
        yield 'blanks after hash' => ["#  \tinclude <a.h>", ['include', '<a.h>', 12]];
        yield 'blanks before body' => ["#define \t X", ['define', 'X', 10]];
        yield 'body keeps blanks inside it' => ['#define F (x)  x ', ['define', 'F (x)  x ', 8]];
        yield 'null directive' => ['#', ['', '', 1]];
        yield 'null directive with blanks' => ['#   ', ['', '', 4]];
        yield 'number after hash' => ['# 42 "a.h"', ['', '42 "a.h"', 2]];
        yield 'name does not start with a digit' => ['#1define', ['', '1define', 1]];
        yield 'name of non-ASCII letters' => ["#d\xC3\xA9fine X", ["d\xC3\xA9fine", 'X', 9]];
        yield 'name with digits and underscores' => ['#include_next2 <a.h>', ['include_next2', '<a.h>', 15]];
        yield 'name ends at a punctuator' => ['#define(X)', ['define', '(X)', 7]];
        yield 'digraph' => ['%:define X', ['define', 'X', 9]];
        yield 'hash followed by digraph' => ['#%:', ['', '%:', 1]];
        yield 'name split by a splice' => ["#def\\\nine X", ['define', 'X', 8]];
    }

    /**
     * @param non-empty-string $line
     * @param array{string, string, int} $expected
     */
    #[DataProvider('directiveDataProvider')]
    public function testDirectiveIsSplitIntoNameAndBody(string $line, array $expected): void
    {
        $directive = (new ChunkFactory(self::createFile($line)))->createDirective($line, 0);

        self::assertSame($expected, [$directive->name, $directive->body, $directive->bodyOffset]);
    }

    public function testDirectiveIsTheLineWithoutSplices(): void
    {
        $content = "int a;\n  #define X \\\n 1\nint b;";
        $file = self::createFile($content);
        $start = (int) \strpos($content, '#');
        $line = \substr($content, $start, (int) \strpos($content, "\nint b") - $start);

        $directive = (new ChunkFactory($file))->createDirective($line, $start);

        self::assertSame('#define X  1', (string) $directive);
        self::assertSame($file, $directive->source);
        self::assertSame($start, $directive->offset);
        self::assertSame(\strpos($content, '1'), $directive->getPhysicalOffset((int) \strpos((string) $directive, '1')));
    }

    public function testSourceChunkIsTheFragmentWithoutSplices(): void
    {
        $content = "#if X\nin\\\nt a;\n#endif\n";
        $file = self::createFile($content);
        $start = (int) \strpos($content, 'in');

        $chunk = (new ChunkFactory($file))->createSourceChunk(\substr($content, $start, 9), $start);

        self::assertSame("int a;\n", (string) $chunk);
        self::assertSame($file, $chunk->source);
        self::assertSame($start, $chunk->offset);
        self::assertSame(\strpos($content, 't a'), $chunk->getPhysicalOffset(2));
    }

    public function testDirectiveKeepsThePlaceOfItsCommentsInTheFile(): void
    {
        $line = '#define X /* c */ 1';
        // The comment of 7 bytes is the space at offset 10
        $directive = (new ChunkFactory(self::createFile($line)))->createDirective('#define X   1', 0, [11], [6]);

        self::assertSame('X   1', $directive->body);
        self::assertSame(\strpos($line, '/*'), $directive->getPhysicalOffset(10));
        self::assertSame(\strpos($line, '1'), $directive->getPhysicalOffset(12));
    }

    public function testSourceChunkKeepsThePlaceOfItsCommentsAndSplicesInTheFile(): void
    {
        $content = "a /* c */ b\\\nc";
        // The comment of 7 bytes is the space at offset 2, the splice follows "b"
        $chunk = (new ChunkFactory(self::createFile($content)))->createSourceChunk("a   b\\\nc", 0, [3], [6]);

        self::assertSame('a   bc', (string) $chunk);
        self::assertSame(\strpos($content, '/*'), $chunk->getPhysicalOffset(2));
        self::assertSame(\strpos($content, 'b'), $chunk->getPhysicalOffset(4));
        self::assertSame(\strrpos($content, 'c'), $chunk->getPhysicalOffset(5));
    }

    public function testSourceChunkWithoutSplicesHasEmptyMap(): void
    {
        $chunk = (new ChunkFactory(self::createFile("int a;\n")))->createSourceChunk("int a;\n", 0);

        self::assertTrue($chunk->map->isEmpty());
    }
}
