<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Reader\Transformer;

use FFI\Preprocessor\Stream\Exception\UnclosedCommentException;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;
use FFI\Preprocessor\Stream\Reader\Transformer\CommentReplacer;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CommentReplacer::class)]
final class CommentReplacerTest extends TestCase
{
    #[DataProvider('commentedTextDataProvider')]
    public function testCommentsAreReplacedBySpaces(string $text, string $expected): void
    {
        $transformation = (new CommentReplacer())->transform(
            self::createUntaggedChunk($text),
            true,
        );

        self::assertSame($expected, $transformation->ready->content);
        self::assertTrue($transformation->rest->isEmpty());
    }

    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function commentedTextDataProvider(): iterable
    {
        yield 'no comment' => ['int a;', 'int a;'];
        yield 'block comment' => ['a/* c */b', 'a b'];
        yield 'empty block comment' => ['a/**/b', 'a b'];
        yield 'block comment over lines' => ["a/* c\nd */b", 'a b'];
        yield 'block comment with stars' => ['a/*** c ***/b', 'a b'];
        yield 'line comment' => ["a // c\nb", "a  \nb"];
        yield 'line comment at the end of the file' => ['a // c', 'a  '];
        yield 'markers inside a line comment' => ["a // /* c\nb", "a  \nb"];
        yield 'line marker inside a block comment' => ["a /* // */ b", 'a   b'];
        yield 'two comments in a row' => ['/*a*//*b*/x', '  x'];
        yield 'division is not a comment' => ['a / b /c', 'a / b /c'];
        yield 'string holds markers' => ['s = "/* c */ // c";', 's = "/* c */ // c";'];
        yield 'string holds escaped quote' => ['s = "a\\"/* c */"; /* d */', 's = "a\\"/* c */";  '];
        yield 'char holds markers' => ["c = '/'; d = '//';", "c = '/'; d = '//';"];
        yield 'unterminated string stops at the end of the line' => ["s = \"a\n/* c */", "s = \"a\n "];
        yield 'apostrophe in text' => ["don't /* c */ x\n// y", "don't /* c */ x\n "];
        yield 'digit separator' => ["x = 1'000 /* c */;", 'x = 1\'000  ;'];
        yield 'prefixed literal' => ['x = u8"/*" /* c */;', 'x = u8"/*"  ;'];
        yield 'header name in angle brackets' => ["#include <a//b.h> // c\n", "#include <a//b.h>  \n"];
        yield 'header name with block marker' => ['#include <a/*b.h> /* c */', '#include <a/*b.h>  '];
        yield 'header name in quotes' => ['#include "a//b.h" // c', '#include "a//b.h"  '];
        yield 'comment before the directive' => ['/* c */ #include <a//b.h>', '  #include <a//b.h>'];
        yield 'comments inside the directive' => ['# /* c */ include /* d */ <a//b.h>', '#   include   <a//b.h>'];
        yield 'include next' => ['#include_next <a//b.h>', '#include_next <a//b.h>'];
        yield 'embed' => ['#embed <a//b.bin> // c', '#embed <a//b.bin>  '];
        yield 'import' => ['#import <a//b.h>', '#import <a//b.h>'];
        yield 'macro named include is not a directive' => ['#define include <a //b', '#define include <a  '];
        yield 'directive name is not include' => ['#includes <a//b>', '#includes <a '];
        yield 'angle brackets in a define' => ['#define A <a //b', '#define A <a  '];
        yield 'has include' => ['#if __has_include(<a//b.h>) // c', '#if __has_include(<a//b.h>)  '];
        yield 'has include next with comments' => ['#if __has_include_next /* c */ ( /* d */ "a//b.h")', '#if __has_include_next   (   "a//b.h")'];
        yield 'has embed' => ['#elif __has_embed(<a//b>) /* c */', '#elif __has_embed(<a//b>)  '];
        yield 'identifier that holds has include' => ['x__has_include(<a //b', 'x__has_include(<a  '];
        yield 'header name only after the directive starts the line' => ['x #include <a //b', 'x #include <a  '];
        yield 'digraph directive' => ['%: include <a//b.h>', '%: include <a//b.h>'];
    }

    public function testUnterminatedCommentIsReportedWithItsPosition(): void
    {
        $text = "int a;\nint /* b";

        try {
            (new CommentReplacer())->transform(self::createUntaggedChunk($text, 100), true);
            self::fail('The unterminated comment is not reported');
        } catch (UnclosedCommentException $e) {
            self::assertSame('Unterminated comment', $e->getMessage());
            self::assertSame(111, $e->offset);
        }
    }

    public function testUnterminatedCommentWaitsForTheNextBlock(): void
    {
        $text = "int a; /* b\nc";

        $transformation = (new CommentReplacer())->transform(
            self::createUntaggedChunk($text),
            false,
        );

        self::assertSame('', $transformation->ready->content);
        self::assertSame($text, $transformation->rest->content);
    }

    public function testLastLineWaitsForTheNextBlock(): void
    {
        $transformation = (new CommentReplacer())->transform(
            self::createUntaggedChunk("a /* c */ b\n#include <a/"),
            false,
        );

        self::assertSame("a   b\n", $transformation->ready->content);
        self::assertSame('#include <a/', $transformation->rest->content);
        self::assertSame(12, $transformation->rest->offset);
    }

    public function testLineWithCommentThatEndsInTheLastLineWaitsWhole(): void
    {
        $text = "x /* a\nb */ y /* c */ z";

        $transformation = (new CommentReplacer())->transform(
            self::createUntaggedChunk($text),
            false,
        );

        self::assertSame('', $transformation->ready->content);
        self::assertSame($text, $transformation->rest->content);
    }

    public function testEveryByteOfTheResultPointsToItsByteOfTheFile(): void
    {
        $file = "a /* c\nd */ b // e\nf";
        $result = (new CommentReplacer())->transform(self::createUntaggedChunk($file), true)->ready;

        self::assertSame("a   b  \nf", $result->content);
        self::assertSame(0, $result->getPhysicalOffset(0));
        self::assertSame(2, $result->getPhysicalOffset(2));
        self::assertSame(11, $result->getPhysicalOffset(3));
        self::assertSame(12, $result->getPhysicalOffset(4));
        self::assertSame(18, $result->getPhysicalOffset(7));
        self::assertSame(19, $result->getPhysicalOffset(8));
    }
}
