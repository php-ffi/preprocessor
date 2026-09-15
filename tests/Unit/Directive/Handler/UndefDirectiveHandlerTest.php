<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive\Handler;

use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Handler\UndefDirectiveHandler;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UndefDirectiveHandler::class)]
final class UndefDirectiveHandlerTest extends DirectiveTestCase
{
    public function testRemovesTheMacro(): void
    {
        $macros = new MacroRepository();
        $macros->define('X');

        $result = (new UndefDirectiveHandler())->handle(
            self::readDirective("#undef X\n"),
            self::createContext(new RecordingDiagnosticListener(), null, $macros),
        );

        self::assertSame([], [...$result]);
        self::assertNull($macros->find('X'));
    }

    public function testUnknownMacroIsHarmless(): void
    {
        $listener = new RecordingDiagnosticListener();

        (new UndefDirectiveHandler())->handle(self::readDirective("#undef X\n"), self::createContext($listener));

        self::assertSame([], $listener->diagnostics);
    }

    public function testExtraTokensAreWarnedAbout(): void
    {
        $listener = new RecordingDiagnosticListener();

        (new UndefDirectiveHandler())->handle(self::readDirective("#undef X Y\n"), self::createContext($listener));

        self::assertCount(1, $listener->diagnostics);
    }

    public function testNameThatIsNotIdentifierIsRejected(): void
    {
        $this->expectException(InvalidDirectiveBodyException::class);

        (new UndefDirectiveHandler())->handle(
            self::readDirective("#undef 1\n"),
            self::createContext(new RecordingDiagnosticListener()),
        );
    }
}
