<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Exception;

use FFI\Preprocessor\Exception\SourceException;
use FFI\Preprocessor\Exception\SyntaxErrorException;
use FFI\Preprocessor\Stream\Chunk\SourceMap;
use FFI\Preprocessor\Stream\SourceDirective;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Contracts\Lexer\Channel;
use Phplrt\Lexer\Token\Token;
use Phplrt\Source\FileSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SyntaxErrorException::class)]
#[CoversClass(SourceException::class)]
final class SyntaxErrorExceptionTest extends TestCase
{
    public function testUnexpectedTokenIsReportedAtItsPlaceInTheFile(): void
    {
        $file = self::createFile("int a;\n#define X @ y\n");
        // "@" at offset 10 of the directive, which starts at offset 7 of the file
        $directive = new SourceDirective('define', 8, '#define X @ y', $file, 7, SourceMap::createEmpty());

        $e = SyntaxErrorException::becauseUnexpectedToken(self::createToken('@', 10), $directive);

        self::assertSame('Syntax error, unexpected "@"', $e->getMessage());
        self::assertSame($file, $e->source);
        self::assertSame(17, $e->offset);
        self::assertSame(1, $e->length);
    }

    public function testTokenSplitBySpliceIsReportedWithTheSplice(): void
    {
        $file = self::createFile("#define X in\\\nt\n");
        $directive = new SourceDirective('define', 8, '#define X int', $file, 0, SourceMap::createFromSplices([12], [2]));

        $e = SyntaxErrorException::becauseUnexpectedToken(self::createToken('int', 10), $directive);

        self::assertSame(10, $e->offset);
        self::assertSame(5, $e->length);
    }

    public function testQuotesOfTokenAreEscapedInMessage(): void
    {
        $directive = new SourceDirective('line', 6, '#line "a"', self::createFile('#line "a"'), 0, SourceMap::createEmpty());

        $e = SyntaxErrorException::becauseUnexpectedToken(self::createToken('"a"', 6), $directive);

        self::assertSame('Syntax error, unexpected "\\"a\\""', $e->getMessage());
    }

    public function testPrintedFailureShowsTheFragmentOfTheFile(): void
    {
        $file = self::createFile("int a;\n#define X @ y\nint b;\n", 'include/test.h');
        $directive = new SourceDirective('define', 8, '#define X @ y', $file, 7, SourceMap::createEmpty());

        $printed = (string) SyntaxErrorException::becauseUnexpectedToken(self::createToken('@', 10), $directive);

        self::assertStringContainsString('Syntax error, unexpected "@"', $printed);
        self::assertStringContainsString('include/test.h:2:11', $printed);
        self::assertStringContainsString('#define X @ y', $printed);
    }

    public function testPrintedFailureWithUnreadableFileHasNoFragment(): void
    {
        $file = new FileSource(__DIR__ . '/missing-file.h');
        $directive = new SourceDirective('define', 8, '#define X @', $file, 0, SourceMap::createEmpty());

        $printed = (string) SyntaxErrorException::becauseUnexpectedToken(self::createToken('@', 10), $directive);

        self::assertStringStartsWith(SyntaxErrorException::class . ': Syntax error, unexpected "@"', $printed);
    }

    /**
     * @param int<0, max> $offset
     */
    private static function createToken(string $value, int $offset): Token
    {
        return new Token(0, 'T_TEST', Channel::Default, $value, $offset);
    }
}
