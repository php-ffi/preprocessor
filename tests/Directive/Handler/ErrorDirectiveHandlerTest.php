<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\Handler\ErrorDirectiveHandler;
use FFI\Preprocessor\Tests\Directive\DirectiveTestCase;
use FFI\Preprocessor\Tests\Directive\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ErrorDirectiveHandler::class)]
final class ErrorDirectiveHandlerTest extends DirectiveTestCase
{
    public function testMessageIsReportedWithItsPlaceAndNothingIsPutOut(): void
    {
        $listener = new RecordingDiagnosticListener();
        $directive = self::readDirective("int x;\n  #error no way\n");

        $result = (new ErrorDirectiveHandler())->handle($directive, self::createContext($listener));

        self::assertSame([], [...$result]);
        self::assertCount(1, $listener->diagnostics);
        self::assertSame(Severity::Error, $listener->diagnostics[0]->severity);
        self::assertSame('#error no way', $listener->diagnostics[0]->message);
        self::assertSame($directive->source, $listener->diagnostics[0]->source);
        self::assertSame(9, $listener->diagnostics[0]->offset);
    }

    public function testEmptyMessageHasNoTrailingBlank(): void
    {
        $listener = new RecordingDiagnosticListener();

        (new ErrorDirectiveHandler())->handle(self::readDirective("#error\n"), self::createContext($listener));

        self::assertSame('#error', $listener->diagnostics[0]->message);
    }
}
