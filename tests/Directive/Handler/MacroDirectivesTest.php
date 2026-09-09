<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Directive\Handler;

use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Handler\DefineDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\UndefDirectiveHandler;
use FFI\Preprocessor\Macro\FunctionLikeMacro;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Macro\ObjectLikeMacro;
use FFI\Preprocessor\Tests\Directive\DirectiveTestCase;
use FFI\Preprocessor\Tests\Directive\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DefineDirectiveHandler::class)]
#[CoversClass(UndefDirectiveHandler::class)]
final class MacroDirectivesTest extends DirectiveTestCase
{
    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function objectLikeDataProvider(): iterable
    {
        yield 'with replacement' => ["#define X  1 + 2  \n", '1 + 2'];
        yield 'empty' => ["#define X\n", ''];
        yield 'parenthesis after blank' => ["#define X (1)\n", '(1)'];
    }

    #[DataProvider('objectLikeDataProvider')]
    public function testObjectLikeMacroIsDefined(string $source, string $replacement): void
    {
        $macros = new MacroRepository();

        $result = (new DefineDirectiveHandler())->handle(
            self::readDirective($source),
            self::createContext(new RecordingDiagnosticListener(), null, $macros),
        );

        self::assertSame([], [...$result]);
        $macro = $macros->find('X');
        self::assertInstanceOf(ObjectLikeMacro::class, $macro);
        self::assertSame($replacement, $macro->replacement);
    }

    /**
     * @return iterable<non-empty-string, array{string, list<string>, string}>
     */
    public static function functionLikeDataProvider(): iterable
    {
        yield 'parameters' => ["#define M(a, b) ((a) > (b) ? (a) : (b))\n", ['1', '2'], '((1) > (2) ? (1) : (2))'];
        yield 'other identifiers stay' => ["#define M(a) f(a) + ab + a1\n", ['1'], 'f(1) + ab + a1'];
        yield 'literals stay' => ["#define M(a) \"a\" 'a' a\n", ['1'], '"a" \'a\' 1'];
        yield 'percent is kept' => ["#define M(a) a % 2\n", ['7'], '7 % 2'];
        yield 'no parameters' => ["#define M() x\n", [''], 'x'];
        yield 'variadic' => ["#define M(f, ...) g(f, __VA_ARGS__)\n", ['x', '1', '2'], 'g(x, 1, 2)'];
        yield 'named variadic' => ["#define M(args...) g(args)\n", ['1', '2'], 'g(1, 2)'];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('functionLikeDataProvider')]
    public function testFunctionLikeMacroIsDefined(string $source, array $arguments, string $expected): void
    {
        $macros = new MacroRepository();

        (new DefineDirectiveHandler())->handle(
            self::readDirective($source),
            self::createContext(new RecordingDiagnosticListener(), null, $macros),
        );

        $macro = $macros->find('M');
        self::assertInstanceOf(FunctionLikeMacro::class, $macro);
        self::assertSame($expected, $macro->replace($arguments));
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function invalidDefineDataProvider(): iterable
    {
        yield 'no name' => ["#define\n"];
        yield 'number as name' => ["#define 1\n"];
        yield 'unclosed list' => ["#define F(a, b\n"];
        yield 'invalid parameter' => ["#define F(1) x\n"];
        yield 'empty parameter' => ["#define F(a,,b) x\n"];
        yield 'duplicated parameter' => ["#define F(a, a) x\n"];
        yield 'parameter after variadic' => ["#define F(..., a) x\n"];
    }

    #[DataProvider('invalidDefineDataProvider')]
    public function testInvalidDefinitionIsRejected(string $source): void
    {
        $this->expectException(InvalidDirectiveBodyException::class);

        (new DefineDirectiveHandler())->handle(
            self::readDirective($source),
            self::createContext(new RecordingDiagnosticListener()),
        );
    }

    public function testUndefRemovesTheMacro(): void
    {
        $macros = new MacroRepository();
        $macros->define('X');

        (new UndefDirectiveHandler())->handle(
            self::readDirective("#undef X\n"),
            self::createContext(new RecordingDiagnosticListener(), null, $macros),
        );

        self::assertNull($macros->find('X'));
    }

    public function testUndefOfUnknownMacroIsHarmless(): void
    {
        $listener = new RecordingDiagnosticListener();

        (new UndefDirectiveHandler())->handle(self::readDirective("#undef X\n"), self::createContext($listener));

        self::assertSame([], $listener->diagnostics);
    }

    public function testUndefWarnsAboutExtraTokens(): void
    {
        $listener = new RecordingDiagnosticListener();

        (new UndefDirectiveHandler())->handle(self::readDirective("#undef X Y\n"), self::createContext($listener));

        self::assertCount(1, $listener->diagnostics);
    }
}
