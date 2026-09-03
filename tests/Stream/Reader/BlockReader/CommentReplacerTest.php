<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Reader\BlockReader;

use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\Reader\BlockReader\CommentReplacer;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CommentReplacer::class)]
final class CommentReplacerTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{non-empty-string, string}>
     */
    public static function lineDataProvider(): iterable
    {
        yield 'no comment' => ['#define X 1', '#define X 1'];
        yield 'block comment' => ['#define X /* c */ 1', '#define X   1'];
        yield 'block comment before the name' => ['# /* c */ define X', '#   define X'];
        yield 'block comment between the parts of a word' => ['#define X a/**/b', '#define X a b'];
        yield 'block comment with new-lines' => ["#define X 1 /* a\nb */ 2", '#define X 1   2'];
        yield 'line comment' => ['#define X 1 // c', '#define X 1  '];
        yield 'line comment with a block comment in it' => ['#define X 1 // a /* b', '#define X 1  '];
        yield 'block comment with a line comment in it' => ['#define X /* // */ 1', '#define X   1'];
        yield 'consecutive comments' => ['#define X /* a *//* b */// c', '#define X    '];
        yield 'digraph' => ['%:define X /* c */', '%:define X  '];
        yield 'division' => ['#define D(a, b) a / b', '#define D(a, b) a / b'];
        yield 'division in a number' => ['#if 1/2 /* c */', '#if 1/2  '];
        yield 'comment opening in a string' => ['#define S "/* c */"', '#define S "/* c */"'];
        yield 'comment opening in a string after an escaped quote' => [
            '#define S "a\\"/*b*/" /* c */',
            '#define S "a\\"/*b*/"  ',
        ];
        yield 'comment opening in a character constant' => ["#define C '/*' /* c */", "#define C '/*'  "];
        yield 'digit separator before a comment' => ["#if 1'000 /* c */", "#if 1'000  "];
        yield 'splices in a block comment' => ["#define X 1 /\\\n* c *\\\n/ 2", '#define X 1   2'];
        yield 'splice in a line comment' => ["#define X 1 // a \\\n b", '#define X 1  '];
        yield 'header name of include' => ['#include <a/*b.h> /* c */', '#include <a/*b.h>  '];
        yield 'header name of include after a comment' => ['#include/* c */<a/*b.h>', '#include <a/*b.h>'];
        yield 'quoted header name of include_next' => ['#include_next "a//b.h" // c', '#include_next "a//b.h"  '];
        yield 'header name of import' => ['#import <a//b.h>', '#import <a//b.h>'];
        yield 'header name of embed' => ['#embed <a/*b> /* c */', '#embed <a/*b>  '];
        yield 'name of the directive split by a splice' => ["#inc\\\nlude <a/*b.h>", "#inc\\\nlude <a/*b.h>"];
        yield 'operand of include that is not a header name' => ['#include X /* c */', '#include X  '];
        yield 'header name of __has_include in if' => [
            '#if __has_include(<a/*b.h>) /* c */',
            '#if __has_include(<a/*b.h>)  ',
        ];
        yield 'header name of __has_embed in elif' => [
            '#elif __has_embed ( /* c */ <a/*b> )',
            '#elif __has_embed (   <a/*b> )',
        ];
        yield 'header name of __has_include_next in if' => [
            '#if __has_include_next("a//b.h") // c',
            '#if __has_include_next("a//b.h")  ',
        ];
        yield 'operand of __has_include that is not a header name' => [
            '#if __has_include(a/*b*/)',
            '#if __has_include(a )',
        ];
        yield 'header name of __has_include in define' => [
            '#define H __has_include(<a/*b.h>) */',
            '#define H __has_include(<a ',
        ];
        yield 'angle in define' => ['#define H <a/*b.h> */', '#define H <a '];
        yield 'header name of __has_include after another operator' => [
            '#if defined X && __has_include(<a/*b.h>) /* c */',
            '#if defined X && __has_include(<a/*b.h>)  ',
        ];
        yield 'header name after a name that only ends like an operator' => [
            '#if x__has_include(<a/*b.h>) */',
            '#if x__has_include(<a ',
        ];
    }

    #[DataProvider('lineDataProvider')]
    public function testCommentsAreReplacedByASpace(string $line, string $expected): void
    {
        $replacement = (new CommentReplacer())->replace($line, self::createFile($line), 0);

        self::assertSame($expected, $replacement->text);
    }

    public function testCommentsTakeTheirPlaceInTheFile(): void
    {
        $line = "#define X /* a */ 1 /\\\n* b *\\\n/ 2 // c";
        $replacement = (new CommentReplacer())->replace($line, self::createFile($line), 0);

        self::assertSame('#define X   1   2  ', $replacement->text);
        // The comments take 7, 11 and 4 bytes of the file
        self::assertSame([11, 15, 19], $replacement->offsets);
        self::assertSame([6, 10, 3], $replacement->sizes);
    }

    public function testLineWithoutCommentsHasNoReplacements(): void
    {
        $replacement = (new CommentReplacer())->replace('#define X 1', self::createFile(''), 0);

        self::assertSame([], $replacement->offsets);
        self::assertSame([], $replacement->sizes);
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string, int<0, max>}>
     */
    public static function unterminatedCommentDataProvider(): iterable
    {
        yield 'block comment' => ['#define X 1 /* c', 12];
        yield 'block comment ended by the slash that opens it' => ['#define X 1 /*/', 12];
        yield 'block comment after a literal' => ['#define X "/*" /* c', 15];
        yield 'block comment after a line comment is not one' => ['#define X 1 // a /*', -1];
    }

    /**
     * @param non-empty-string $line
     */
    #[DataProvider('unterminatedCommentDataProvider')]
    public function testUnterminatedCommentIsReportedWithItsPlaceInTheFile(string $line, int $offset): void
    {
        $replacer = new CommentReplacer();

        if ($offset < 0) {
            self::assertSame('#define X 1  ', $replacer->replace($line, self::createFile($line), 0)->text);

            return;
        }

        try {
            $replacer->replace($line, self::createFile($line), 100);
        } catch (SyntaxErrorException $e) {
            self::assertSame('Unterminated comment', $e->getMessage());
            self::assertSame(100 + $offset, $e->offset);

            return;
        }

        self::fail('SyntaxErrorException has not been thrown');
    }
}
