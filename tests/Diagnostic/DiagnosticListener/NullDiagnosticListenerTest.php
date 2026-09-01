<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListener\NullDiagnosticListener;
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\VirtualSource;
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

    public function testPreprocessingGoesOnAfterError(): void
    {
        $preprocessor = new Preprocessor(listener: new NullDiagnosticListener());

        $output = (string) $preprocessor->preprocess(VirtualSource::createFromString('a.h', "#error stop\nint a;\n"));

        self::assertStringContainsString('int a;', $output);
    }
}
