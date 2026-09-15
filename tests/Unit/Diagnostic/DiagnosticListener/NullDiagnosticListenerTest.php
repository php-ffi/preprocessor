<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListener\NullDiagnosticListener;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(NullDiagnosticListener::class)]
final class NullDiagnosticListenerTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{Severity}>
     */
    public static function severityDataProvider(): iterable
    {
        foreach (Severity::cases() as $severity) {
            yield $severity->value => [$severity];
        }
    }

    #[DataProvider('severityDataProvider')]
    public function testDiagnosticOfAnySeverityIsSkipped(Severity $severity): void
    {
        (new NullDiagnosticListener())->notify(new Diagnostic($severity, 'text', self::createFile(''), 0));

        $this->expectNotToPerformAssertions();
    }
}
