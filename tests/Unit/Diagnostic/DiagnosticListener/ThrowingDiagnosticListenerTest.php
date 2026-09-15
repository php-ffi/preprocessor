<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListener\ThrowingDiagnosticListener;
use FFI\Preprocessor\Diagnostic\Exception\DiagnosticException;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ThrowingDiagnosticListener::class)]
#[CoversClass(DiagnosticException::class)]
final class ThrowingDiagnosticListenerTest extends TestCase
{
    public function testErrorStopsPreprocessing(): void
    {
        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage('File <a.h> not found');

        (new ThrowingDiagnosticListener())->notify(new Diagnostic(Severity::Error, 'File <a.h> not found', self::createFile(''), 0));
    }

    public function testWarningIsSkipped(): void
    {
        (new ThrowingDiagnosticListener())->notify(new Diagnostic(Severity::Warning, 'text', self::createFile(''), 0));

        $this->expectNotToPerformAssertions();
    }
}
