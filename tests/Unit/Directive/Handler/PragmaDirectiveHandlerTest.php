<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive\Handler;

use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\GeneratedChunk;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PragmaDirectiveHandler::class)]
final class PragmaDirectiveHandlerTest extends DirectiveTestCase
{
    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function pragmaDataProvider(): iterable
    {
        yield 'with body' => ["#pragma pack(push, 1)\n", "#pragma pack(push, 1)\n"];
        yield 'blanks around' => ["#  pragma   once  \n", "#pragma once\n"];
        yield 'empty body' => ["#pragma\n", "#pragma\n"];
        yield 'unknown pragma' => ["#pragma foo bar\n", "#pragma foo bar\n"];
    }

    #[DataProvider('pragmaDataProvider')]
    public function testDirectiveIsPassedToTheOutput(string $source, string $expected): void
    {
        $listener = new RecordingDiagnosticListener();

        $chunks = [...(new PragmaDirectiveHandler())->handle(
            self::readDirective($source),
            self::createContext($listener),
        )];

        self::assertCount(1, $chunks);
        self::assertInstanceOf(GeneratedChunk::class, $chunks[0]);
        self::assertSame($expected, $chunks[0]->content);
        self::assertSame([], $listener->diagnostics);
    }
}
