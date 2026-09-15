<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive\Handler;

use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Handler\DefineDirectiveHandler;
use FFI\Preprocessor\Macro\Executor\MacroExpander;
use FFI\Preprocessor\Macro\FunctionLikeMacro\CompiledFunctionLikeMacro;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Macro\ObjectLikeMacro;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DefineDirectiveHandler::class)]
final class DefineDirectiveHandlerTest extends DirectiveTestCase
{
    /**
     * @param list<string> $directives
     */
    private static function expand(array $directives, string $text): string
    {
        $macros = new MacroRepository();
        $handler = new DefineDirectiveHandler();

        foreach ($directives as $directive) {
            $result = $handler->handle(
                self::readDirective($directive . "\n"),
                self::createContext(new RecordingDiagnosticListener(), null, $macros),
            );

            self::assertSame([], [...$result]);
        }

        return (new MacroExpander($macros))->expand($text);
    }

    /**
     * @return iterable<non-empty-string, array{list<string>, string, string}>
     */
    public static function expansionDataProvider(): iterable
    {
        yield 'object like' => [['#define ONE 1'], 'ONE', '1'];
        yield 'object like without replacement' => [['#define EMPTY'], '[EMPTY]', '[]'];
        yield 'object like with blanks around the replacement' => [['#define X   a  b   '], 'X', 'a  b'];
        yield 'object like with the parenthesis after a blank' => [['#define X (a) b'], 'X', '(a) b'];
        yield 'object like with a hash' => [['#define X a # b'], 'X', 'a # b'];
        yield 'object like with a percent' => [['#define X 10 % 3'], 'X', '10 % 3'];
        yield 'object like concatenation' => [['#define X a ## b'], 'X', 'ab'];
        yield 'function like' => [['#define ADD(a, b) ((a) + (b))'], 'ADD(1, 2)', '((1) + (2))'];
        yield 'function like without parameters' => [['#define F() 0'], 'F()', '0'];
        yield 'function like without replacement' => [['#define F(x)'], '[F(1)]', '[]'];
        yield 'function like with a percent' => [['#define MOD(a, b) a % b'], 'MOD(7, 3)', '7 % 3'];
        yield 'parameter in the order of use' => [['#define SWAP(a, b) b a'], 'SWAP(1, 2)', '2 1'];
        yield 'parameter used twice' => [['#define TWICE(a) a a'], 'TWICE(1)', '1 1'];
        yield 'parameter is expanded' => [['#define ONE 1', '#define ID(x) x'], 'ID(ONE)', '1'];
        yield 'parameter in a literal stays' => [['#define S(x) "x" \'x\' x'], 'S(1)', '"x" \'x\' 1'];
        yield 'parameter in a number stays' => [['#define N(x1F) 0x1F x1F'], 'N(2)', '0x1F 2'];
        yield 'stringification' => [['#define STR(x) #x'], 'STR(a   b)', '"a b"'];
        yield 'stringification with a blank after the hash' => [['#define STR(x) # x'], 'STR(a)', '"a"'];
        yield 'stringification does not expand' => [['#define N 5', '#define STR(x) #x'], 'STR(N)', '"N"'];
        yield 'stringification of an expanded argument' => [
            ['#define N 5', '#define STR(x) #x', '#define XSTR(x) STR(x)'],
            'XSTR(N)',
            '"5"',
        ];
        yield 'concatenation' => [['#define CAT(a, b) a ## b'], 'CAT(x, y)', 'xy'];
        yield 'concatenation with blanks' => [['#define CAT(a, b) a   ##   b'], 'CAT(x, y)', 'xy'];
        yield 'concatenation does not expand' => [['#define N 5', '#define CAT(a, b) a ## b'], 'CAT(N, 1)', 'N1'];
        yield 'concatenation with a text' => [['#define PREFIXED(a) prefix_ ## a ## _suffix'], 'PREFIXED(x)', 'prefix_x_suffix'];
        yield 'concatenation with an empty argument' => [['#define CAT(a, b) a ## b'], 'CAT(, y)', 'y'];
        yield 'operand of concatenation is expanded elsewhere' => [
            ['#define N 5', '#define F(a, b) a ## b a'],
            'F(N, 1)',
            'N1 5',
        ];
        yield 'variadic' => [['#define LOG(fmt, ...) log(fmt, __VA_ARGS__)'], 'LOG("x", 1, 2)', 'log("x", 1, 2)'];
        yield 'variadic only' => [['#define ALL(...) [__VA_ARGS__]'], 'ALL(1, 2)', '[1, 2]'];
        yield 'redefinition' => [['#define X 1', '#define X 2'], 'X', '2'];
    }

    /**
     * @param list<string> $directives
     */
    #[DataProvider('expansionDataProvider')]
    public function testDefinedMacroIsExpanded(array $directives, string $text, string $expected): void
    {
        self::assertSame($expected, self::expand($directives, $text));
    }

    public function testMacroWithoutParametersIsObjectLike(): void
    {
        $macros = new MacroRepository();

        (new DefineDirectiveHandler())->handle(
            self::readDirective("#define X 1\n"),
            self::createContext(new RecordingDiagnosticListener(), null, $macros),
        );

        self::assertInstanceOf(ObjectLikeMacro::class, $macros->find('X'));
    }

    public function testMacroWithParametersIsFunctionLike(): void
    {
        $macros = new MacroRepository();

        (new DefineDirectiveHandler())->handle(
            self::readDirective("#define F(a, ...) a\n"),
            self::createContext(new RecordingDiagnosticListener(), null, $macros),
        );

        $macro = $macros->find('F');

        self::assertInstanceOf(CompiledFunctionLikeMacro::class, $macro);
        self::assertSame(2, $macro->args);
        self::assertTrue($macro->isVariadic);
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function invalidDefinitionDataProvider(): iterable
    {
        yield 'name is not an identifier' => ['#define 1'];
        yield 'no name' => ['#define'];
        yield 'parameter list is not closed' => ['#define F(a, b a'];
        yield 'parameter is not an identifier' => ['#define F(a, 1) a'];
        yield 'parameter is empty' => ['#define F(a, ) a'];
        yield 'parameter is repeated' => ['#define F(a, a) a'];
        yield 'variadic is not the last' => ['#define F(..., a) a'];
        yield 'hash without a parameter' => ['#define F(a) #b'];
        yield 'hash at the end' => ['#define F(a) a #'];
        yield 'concatenation at the start' => ['#define F(a) ## a'];
        yield 'concatenation at the end' => ['#define F(a) a ##'];
        yield 'concatenation at the start of an object like' => ['#define X ## a'];
    }

    #[DataProvider('invalidDefinitionDataProvider')]
    public function testInvalidDefinitionIsRejected(string $directive): void
    {
        $this->expectException(InvalidDirectiveBodyException::class);

        (new DefineDirectiveHandler())->handle(
            self::readDirective($directive . "\n"),
            self::createContext(new RecordingDiagnosticListener()),
        );
    }

    public function testRejectedDefinitionDefinesNothing(): void
    {
        $macros = new MacroRepository();

        try {
            (new DefineDirectiveHandler())->handle(
                self::readDirective("#define F(a) #b\n"),
                self::createContext(new RecordingDiagnosticListener(), null, $macros),
            );
        } catch (InvalidDirectiveBodyException) {
        }

        self::assertNull($macros->find('F'));
    }
}
