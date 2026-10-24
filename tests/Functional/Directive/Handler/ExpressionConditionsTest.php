<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Functional\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\Handler\DirectiveHandler\ConditionEvaluator;
use FFI\Preprocessor\Directive\Handler\ElseIfDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\IfDirectiveHandler;
use FFI\Preprocessor\Directive\Exception\InvalidConditionException;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Macro\FunctionLikeMacro\TemplateFunctionLikeMacro;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IfDirectiveHandler::class)]
#[CoversClass(ElseIfDirectiveHandler::class)]
#[CoversClass(ConditionEvaluator::class)]
final class ExpressionConditionsTest extends DirectiveTestCase
{
    private static function createMacros(): MacroRepository
    {
        $macros = new MacroRepository();
        $macros->defineByString('ONE', '1');
        $macros->defineByString('ZERO', '0');
        $macros->defineByString('VERSION', '305');
        $macros->defineByMacro('ADD', new TemplateFunctionLikeMacro('(%s + %s)', 2));

        return $macros;
    }

    private static function createFiles(): VirtualFileSystem
    {
        $files = new VirtualFileSystem();
        $files->add('a.h', VirtualSource::createFromString('a.h', ''));

        return $files;
    }

    /**
     * @return iterable<non-empty-string, array{string, bool}>
     */
    public static function conditionDataProvider(): iterable
    {
        yield 'number' => ['1', true];
        yield 'zero' => ['0', false];
        yield 'arithmetic' => ['1 + 2 == 3', true];
        yield 'comparison' => ['2 < 1', false];
        yield 'conditional' => ['0 ? 0 : 5', true];
        yield 'character constant' => ["'a' == 97", true];
        yield 'prefixed character constant' => ["L'a' == 97", true];
        yield 'object like macro' => ['ONE', true];
        yield 'zero macro' => ['ZERO', false];
        yield 'macro in an expression' => ['VERSION >= 300', true];
        yield 'function like macro' => ['ADD(1, ONE) == 2', true];
        yield 'unknown identifier is zero' => ['UNKNOWN', false];
        yield 'unknown identifier in an expression' => ['UNKNOWN + 1', true];
        yield 'true is one' => ['true', true];
        yield 'false is zero' => ['false', false];
        yield 'digits with a letter are a number' => ['0x1F == 31', true];
        yield 'defined' => ['defined ONE', true];
        yield 'defined in parentheses' => ['defined(ONE)', true];
        yield 'defined with blanks' => ['defined (  ONE  )', true];
        yield 'defined of an unknown name' => ['defined UNKNOWN', false];
        yield 'not defined' => ['!defined UNKNOWN', true];
        yield 'defined name is not expanded' => ['defined ZERO', true];
        yield 'defined with a condition' => ['defined(ONE) && VERSION > 300', true];
        yield 'name that ends with defined' => ['undefined', false];
        yield 'has include of a found file' => ['__has_include("a.h")', true];
        yield 'has include of a found angled file' => ['__has_include(<a.h>)', true];
        yield 'has include of a missing file' => ['__has_include("b.h")', false];
        yield 'has include with a condition' => ['defined(ONE) && __has_include(<b.h>)', false];
    }

    #[DataProvider('conditionDataProvider')]
    public function testSectionIsOpenedByTheValueOfTheExpression(string $condition, bool $isEnabled): void
    {
        $output = new OutputStack();

        $result = (new IfDirectiveHandler())->handle(
            self::readDirective("#if $condition\n"),
            self::createContext(new RecordingDiagnosticListener(), $output, self::createMacros(), self::createFiles()),
        );

        self::assertSame([], [...$result]);
        self::assertSame($isEnabled, $output->isEnabled());
        self::assertTrue($output->hasSection());
    }

    public function testSectionInDisabledBranchIsOpenedWithoutEvaluation(): void
    {
        $output = new OutputStack();
        $output->push(false);

        (new IfDirectiveHandler())->handle(
            self::readDirective("#if 1 +\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );

        self::assertFalse($output->isEnabled());
    }

    public function testElifTakesTheBranchOfTheTrueExpression(): void
    {
        $output = new OutputStack();
        $output->push(false);

        (new ElseIfDirectiveHandler())->handle(
            self::readDirective("#elif ONE\n"),
            self::createContext(new RecordingDiagnosticListener(), $output, self::createMacros()),
        );

        self::assertTrue($output->isEnabled());
    }

    public function testElifSkipsTheBranchOfTheFalseExpression(): void
    {
        $output = new OutputStack();
        $output->push(false);

        (new ElseIfDirectiveHandler())->handle(
            self::readDirective("#elif ZERO\n"),
            self::createContext(new RecordingDiagnosticListener(), $output, self::createMacros()),
        );

        self::assertFalse($output->isEnabled());
    }

    public function testElifAfterTakenBranchIsSkippedWithoutEvaluation(): void
    {
        $output = new OutputStack();
        $output->push(true);

        (new ElseIfDirectiveHandler())->handle(
            self::readDirective("#elif 1 +\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );

        self::assertFalse($output->isEnabled());
    }

    public function testElifWithoutSectionIsRejected(): void
    {
        $this->expectException(MisplacedDirectiveException::class);
        $this->expectExceptionMessage('#elif without #if');

        (new ElseIfDirectiveHandler())->handle(
            self::readDirective("#elif 1\n"),
            self::createContext(new RecordingDiagnosticListener()),
        );
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function invalidConditionDataProvider(): iterable
    {
        yield 'empty' => ['#if'];
        yield 'dangling operator' => ['#if 1 +'];
        yield 'unclosed parenthesis' => ['#if (1'];
        yield 'string literal' => ['#if "a"'];
        yield 'call of an unknown name' => ['#if UNKNOWN(1)'];
    }

    #[DataProvider('invalidConditionDataProvider')]
    public function testInvalidExpressionIsRejected(string $directive): void
    {
        $this->expectException(InvalidConditionException::class);

        (new IfDirectiveHandler())->handle(
            self::readDirective($directive . "\n"),
            self::createContext(new RecordingDiagnosticListener()),
        );
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function invalidDefinedDataProvider(): iterable
    {
        yield 'without a name' => ['#if defined'];
        yield 'with a number' => ['#if defined 1'];
        yield 'with an unclosed parenthesis' => ['#if defined(ONE'];
    }

    #[DataProvider('invalidDefinedDataProvider')]
    public function testDefinedWithoutIdentifierIsRejected(string $directive): void
    {
        $this->expectException(InvalidDirectiveBodyException::class);
        $this->expectExceptionMessage('"defined" expects an identifier');

        (new IfDirectiveHandler())->handle(
            self::readDirective($directive . "\n"),
            self::createContext(new RecordingDiagnosticListener(), null, self::createMacros()),
        );
    }
}
