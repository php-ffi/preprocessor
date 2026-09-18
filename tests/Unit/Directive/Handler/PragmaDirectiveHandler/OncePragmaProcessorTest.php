<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive\Handler\PragmaDirectiveHandler;

use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler\OncePragmaProcessor;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use FFI\Preprocessor\Tests\Support\RecordingSourceExecutor;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OncePragmaProcessor::class)]
final class OncePragmaProcessorTest extends DirectiveTestCase
{
    public function testOnlyOnceIsSupported(): void
    {
        $processor = new OncePragmaProcessor();

        self::assertTrue($processor->supports('once'));
        self::assertFalse($processor->supports('pack(1)'));
        self::assertFalse($processor->supports(''));
    }

    public function testFileIsSkippedAndNothingIsOutput(): void
    {
        $executor = new RecordingSourceExecutor();
        $directive = self::readDirective("#pragma once\n");

        $chunks = [...(new OncePragmaProcessor())->apply(
            $directive,
            self::createContext(new RecordingDiagnosticListener(), includer: $executor),
        )];

        self::assertSame([], $chunks);
        self::assertSame([$directive->source], $executor->skipped);
    }
}
