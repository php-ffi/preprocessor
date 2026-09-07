<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Directive\Handler\ElseDirectiveHandler;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Tests\Directive\DirectiveTestCase;
use FFI\Preprocessor\Tests\Directive\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ElseDirectiveHandler::class)]
final class ElseDirectiveHandlerTest extends DirectiveTestCase
{
    public function testEnablesTheOutputAfterDisabledBranch(): void
    {
        $output = new OutputStack();
        $output->push(false);

        $result = (new ElseDirectiveHandler())->handle(
            self::readDirective("#else\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );

        self::assertSame([], [...$result]);
        self::assertTrue($output->isEnabled());
        self::assertTrue($output->hasLastBranch());
    }

    public function testDisablesTheOutputAfterEnabledBranch(): void
    {
        $output = new OutputStack();
        $output->push(true);

        (new ElseDirectiveHandler())->handle(
            self::readDirective("#else\n"),
            self::createContext(new RecordingDiagnosticListener(), $output),
        );

        self::assertFalse($output->isEnabled());
    }

    public function testExtraTokensAreWarnedAbout(): void
    {
        $output = new OutputStack();
        $output->push(true);
        $listener = new RecordingDiagnosticListener();

        (new ElseDirectiveHandler())->handle(self::readDirective("#else X\n"), self::createContext($listener, $output));

        self::assertCount(1, $listener->diagnostics);
        self::assertSame(Severity::Warning, $listener->diagnostics[0]->severity);
        self::assertSame('Extra tokens at the end of #else directive', $listener->diagnostics[0]->message);
        self::assertSame(6, $listener->diagnostics[0]->offset);
    }

    public function testElseWithoutSectionIsRejectedWithItsPlace(): void
    {
        $directive = self::readDirective("#else\n");

        try {
            (new ElseDirectiveHandler())->handle($directive, self::createContext(new RecordingDiagnosticListener()));
            self::fail('The exception is expected');
        } catch (MisplacedDirectiveException $e) {
            self::assertSame('#else without #if', $e->getMessage());
            self::assertSame($directive->source, $e->source);
            self::assertSame(0, $e->offset);
        }
    }

    public function testSecondElseIsRejected(): void
    {
        $output = new OutputStack();
        $output->push(true);
        $handler = new ElseDirectiveHandler();
        $context = self::createContext(new RecordingDiagnosticListener(), $output);
        $handler->handle(self::readDirective("#else\n"), $context);

        $this->expectException(MisplacedDirectiveException::class);
        $this->expectExceptionMessage('#else after #else');

        $handler->handle(self::readDirective("#else\n"), $context);
    }
}
