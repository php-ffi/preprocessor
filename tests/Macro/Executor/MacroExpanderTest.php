<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Macro\Executor;

use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Macro\Executor\MacroExpander;
use FFI\Preprocessor\Macro\FunctionLikeMacro\TemplateFunctionLikeMacro;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(MacroExpander::class)]
final class MacroExpanderTest extends TestCase
{
    private static function createExpander(): MacroExpander
    {
        $macros = new MacroRepository();
        $macros->defineByString('ONE', '1');
        $macros->defineByString('TWO', 'ONE + ONE');
        $macros->defineByString('SELF', 'SELF + 1');
        $macros->defineByString('PING', 'PONG');
        $macros->defineByString('PONG', 'PING');
        $macros->defineByString('FN', 'ID');
        $macros->defineByMacro('ID', new TemplateFunctionLikeMacro('%s', 1));
        $macros->defineByMacro('ADD', new TemplateFunctionLikeMacro('(%s + %s)', 2));
        $macros->defineByMacro('NONE', new TemplateFunctionLikeMacro('0', 0));
        $macros->defineByMacro('LOG', new TemplateFunctionLikeMacro('log(%s, %s)', 2, true));
        $macros->defineByMacro('F', new TemplateFunctionLikeMacro('F(%s)', 1));
        $macros->defineByMacro('V3', new TemplateFunctionLikeMacro('%s%s%s', 3, true));

        return new MacroExpander($macros);
    }

    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function expansionDataProvider(): iterable
    {
        yield 'no macros' => ['int x = 1;', 'int x = 1;'];
        yield 'object like' => ['x = ONE;', 'x = 1;'];
        yield 'rescanned' => ['TWO', '1 + 1'];
        yield 'recursion stops' => ['SELF', 'SELF + 1'];
        yield 'mutual recursion stops' => ['PING', 'PING'];
        yield 'function like' => ['ADD(1, 2)', '(1 + 2)'];
        yield 'name without parentheses stays' => ['ADD + 1', 'ADD + 1'];
        yield 'blanks before parentheses' => ["ID  \n (3)", '3'];
        yield 'nested parentheses' => ['ID((1, 2))', '(1, 2)'];
        yield 'argument is expanded' => ['ID(ONE)', '1'];
        yield 'nested invocations' => ['ADD(ID(1), ID(2))', '(1 + 2)'];
        yield 'macro in its own argument' => ['F(F(1))', 'F(F(1))'];
        yield 'macro expanded to a function like name' => ['FN(5)', '5'];
        yield 'string is not expanded' => ['"ONE" \'ONE\' u8"ONE"', '"ONE" \'ONE\' u8"ONE"'];
        yield 'comma in string is not a separator' => ['ID("a,b")', '"a,b"'];
        yield 'number is not an identifier' => ['0x1ONE 1e+ONE', '0x1ONE 1e+ONE'];
        yield 'no parameters' => ['NONE()', '0'];
        yield 'variadic tail is joined' => ['LOG(a, b, c)', 'log(a, b, c)'];
        yield 'variadic tail is omitted' => ['LOG(a)', 'log(a, )'];
    }

    #[DataProvider('expansionDataProvider')]
    public function testTextIsExpanded(string $text, string $expected): void
    {
        self::assertSame($expected, self::createExpander()->expand($text));
    }

    public function testUnclosedArgumentListIsRejected(): void
    {
        $this->expectException(MacroInvocationException::class);
        $this->expectExceptionMessage('Argument list of macro "ADD" is not closed');

        self::createExpander()->expand('ADD(1, 2');
    }

    public function testTooManyArgumentsAreRejected(): void
    {
        $this->expectException(MacroInvocationException::class);
        $this->expectExceptionMessage('Macro "ID" takes 1 arguments, 2 given');

        self::createExpander()->expand('ID(1, 2)');
    }

    public function testTooFewArgumentsAreRejected(): void
    {
        $this->expectException(MacroInvocationException::class);
        $this->expectExceptionMessage('Macro "ADD" takes 2 arguments, 1 given');

        self::createExpander()->expand('ADD(1)');
    }

    public function testTooFewArgumentsOfVariadicAreRejected(): void
    {
        $this->expectException(MacroInvocationException::class);
        $this->expectExceptionMessage('Macro "V3" takes at least 2 arguments, 1 given');

        self::createExpander()->expand('V3(a)');
    }
}
