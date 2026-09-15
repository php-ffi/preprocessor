<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Directive\Handler\EndifDirectiveHandler;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EndifDirectiveHandler::class)]
final class EndifDirectiveHandlerTest extends DirectiveTestCase
{
    public function testClosesTheCurrentSection(): void
    {
        $output = new OutputStack();
        $output->push(false);
        $listener = new RecordingDiagnosticListener();

        $result = (new EndifDirectiveHandler())->handle(
            self::readDirective("#endif\n"),
            self::createContext($listener, $output),
        );

        self::assertSame([], [...$result]);
        self::assertFalse($output->hasSection());
        self::assertTrue($output->isEnabled());
        self::assertSame([], $listener->diagnostics);
    }

    public function testExtraTokensAreWarnedAbout(): void
    {
        $output = new OutputStack();
        $output->push(true);
        $listener = new RecordingDiagnosticListener();

        (new EndifDirectiveHandler())->handle(
            self::readDirective("#endif  FOO BAR \n"),
            self::createContext($listener, $output),
        );

        self::assertFalse($output->hasSection());
        self::assertCount(1, $listener->diagnostics);
        self::assertSame(Severity::Warning, $listener->diagnostics[0]->severity);
        self::assertSame('Extra tokens at the end of #endif directive', $listener->diagnostics[0]->message);
        self::assertSame(8, $listener->diagnostics[0]->offset);
        self::assertSame(7, $listener->diagnostics[0]->length);
    }

    public function testEndifWithoutSectionIsRejectedWithItsPlace(): void
    {
        $directive = self::readDirective("int x;\n  #endif\n");

        try {
            (new EndifDirectiveHandler())->handle($directive, self::createContext(new RecordingDiagnosticListener()));
            self::fail('The exception is expected');
        } catch (MisplacedDirectiveException $e) {
            self::assertSame('#endif without #if', $e->getMessage());
            self::assertSame($directive->source, $e->source);
            self::assertSame(9, $e->offset);
        }
    }
}
