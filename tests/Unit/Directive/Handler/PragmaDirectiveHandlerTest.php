<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\Directive\Handler;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\PragmaDirectiveHandler\PragmaProcessorInterface;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
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
        yield 'blanks around' => ["#  pragma   pack  \n", "#pragma pack\n"];
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

    public function testSupportingProcessorReplacesTheDefaultBehaviour(): void
    {
        $processor = new class implements PragmaProcessorInterface {
            public function supports(string $body): bool
            {
                return $body === 'custom';
            }

            public function apply(DirectiveChunk $directive, DirectiveContext $context): iterable
            {
                return [new GeneratedChunk("custom\n", $directive->source, 0)];
            }
        };

        $handler = new PragmaDirectiveHandler([$processor]);
        $context = self::createContext(new RecordingDiagnosticListener());

        $custom = [...$handler->handle(self::readDirective("#pragma custom\n"), $context)];
        $other = [...$handler->handle(self::readDirective("#pragma other\n"), $context)];

        self::assertSame("custom\n", $custom[0]->content);
        self::assertSame("#pragma other\n", $other[0]->content);
    }
}
