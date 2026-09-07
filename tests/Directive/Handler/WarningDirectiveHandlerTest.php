<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\Handler\WarningDirectiveHandler;
use FFI\Preprocessor\Tests\Directive\DirectiveTestCase;
use FFI\Preprocessor\Tests\Directive\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WarningDirectiveHandler::class)]
final class WarningDirectiveHandlerTest extends DirectiveTestCase
{
    public function testMessageIsReportedWithItsPlaceAndNothingIsPutOut(): void
    {
        $listener = new RecordingDiagnosticListener();
        $directive = self::readDirective("int x;\n  #warning no way\n");

        $result = (new WarningDirectiveHandler())->handle($directive, self::createContext($listener));

        self::assertSame([], [...$result]);
        self::assertCount(1, $listener->diagnostics);
        self::assertSame(Severity::Warning, $listener->diagnostics[0]->severity);
        self::assertSame('#warning no way', $listener->diagnostics[0]->message);
        self::assertSame($directive->source, $listener->diagnostics[0]->source);
        self::assertSame(9, $listener->diagnostics[0]->offset);
    }

    public function testEmptyMessageHasNoTrailingBlank(): void
    {
        $listener = new RecordingDiagnosticListener();

        (new WarningDirectiveHandler())->handle(self::readDirective("#warning\n"), self::createContext($listener));

        self::assertSame('#warning', $listener->diagnostics[0]->message);
    }
}
