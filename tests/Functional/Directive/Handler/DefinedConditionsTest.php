<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Functional\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\Handler\ElseIfDefinedDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\IfDefinedDirectiveHandler;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IfDefinedDirectiveHandler::class)]
#[CoversClass(ElseIfDefinedDirectiveHandler::class)]
final class DefinedConditionsTest extends DirectiveTestCase
{
    /**
     * @return iterable<non-empty-string, array{string, bool}>
     */
    public static function openingDataProvider(): iterable
    {
        yield 'ifdef defined' => ["#ifdef X\n", true];
        yield 'ifdef undefined' => ["#ifdef Y\n", false];
        yield 'ifndef defined' => ["#ifndef X\n", false];
        yield 'ifndef undefined' => ["#ifndef Y\n", true];
    }

    #[DataProvider('openingDataProvider')]
    public function testSectionIsOpenedByWhetherTheMacroIsDefined(string $source, bool $isEnabled): void
    {
        $macros = new MacroRepository();
        $macros->define('X');
        $output = new OutputStack();

        (new IfDefinedDirectiveHandler())->handle(
            self::readDirective($source),
            self::createContext(new RecordingDiagnosticListener(), $output, $macros),
        );

        self::assertSame($isEnabled, $output->isEnabled());
        self::assertTrue($output->hasSection());
    }

    public function testSectionInDisabledBranchIsOpenedWithoutReadingTheName(): void
    {
        $output = new OutputStack();
        $output->push(false);

        (new IfDefinedDirectiveHandler())->handle(
            self::readDirective("#ifdef 42\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );

        self::assertFalse($output->isEnabled());
        $output->pop();
        self::assertFalse($output->isEnabled());
    }

    public function testExtraTokensAfterTheNameAreWarnedAbout(): void
    {
        $listener = new RecordingDiagnosticListener();

        (new IfDefinedDirectiveHandler())->handle(
            self::readDirective("#ifdef X Y\n"),
            self::createContext($listener),
        );

        self::assertCount(1, $listener->diagnostics);
        self::assertSame(9, $listener->diagnostics[0]->offset);
    }

    public function testNameThatIsNotIdentifierIsRejectedWithItsPlace(): void
    {
        $directive = self::readDirective("#ifdef 42\n");

        try {
            (new IfDefinedDirectiveHandler())->handle($directive, self::createContext(new RecordingDiagnosticListener()));
            self::fail('The exception is expected');
        } catch (InvalidDirectiveBodyException $e) {
            self::assertSame('#ifdef expects an identifier as a macro name', $e->getMessage());
            self::assertSame(7, $e->offset);
        }
    }

    public function testElifdefTakesTheBranchOfTheDefinedMacro(): void
    {
        $macros = new MacroRepository();
        $macros->define('X');
        $output = new OutputStack();
        $output->push(false);

        (new ElseIfDefinedDirectiveHandler())->handle(
            self::readDirective("#elifdef X\n"),
            self::createContext(new RecordingDiagnosticListener(), $output, $macros),
        );

        self::assertTrue($output->isEnabled());
    }

    public function testElifndefTakesTheBranchOfTheUndefinedMacro(): void
    {
        $output = new OutputStack();
        $output->push(false);

        (new ElseIfDefinedDirectiveHandler())->handle(
            self::readDirective("#elifndef X\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );

        self::assertTrue($output->isEnabled());
    }

    public function testBranchIsSkippedAfterTakenOneWithoutReadingTheName(): void
    {
        $output = new OutputStack();
        $output->push(true);

        (new ElseIfDefinedDirectiveHandler())->handle(
            self::readDirective("#elifdef 42\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );

        self::assertFalse($output->isEnabled());
    }

    public function testElifdefWithoutSectionIsRejected(): void
    {
        $this->expectException(MisplacedDirectiveException::class);
        $this->expectExceptionMessage('#elifdef without #if');

        (new ElseIfDefinedDirectiveHandler())->handle(
            self::readDirective("#elifdef X\n"),
            self::createContext(new RecordingDiagnosticListener()),
        );
    }

    public function testElifndefAfterElseIsRejected(): void
    {
        $output = new OutputStack();
        $output->push(false);
        $output->swapLast();

        $this->expectException(MisplacedDirectiveException::class);
        $this->expectExceptionMessage('#elifndef after #else');

        (new ElseIfDefinedDirectiveHandler())->handle(
            self::readDirective("#elifndef X\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );
    }
}
