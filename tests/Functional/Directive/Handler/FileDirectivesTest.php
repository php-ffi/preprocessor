<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Functional\Directive\Handler;

use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Directive\DirectiveHandlerInterface;
use FFI\Preprocessor\Directive\Exception\InvalidDirectiveBodyException;
use FFI\Preprocessor\Directive\Handler\EmbedDirectiveHandler;
use FFI\Preprocessor\Directive\Handler\IncludeDirectiveHandler;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Tests\Support\DirectiveTestCase;
use FFI\Preprocessor\Tests\Support\RecordingDiagnosticListener;
use FFI\Preprocessor\Tests\Support\RecordingSourceExecutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Phplrt\Source\VirtualSource;

#[CoversClass(IncludeDirectiveHandler::class)]
#[CoversClass(EmbedDirectiveHandler::class)]
final class FileDirectivesTest extends DirectiveTestCase
{
    /**
     * @return iterable<non-empty-string, array{DirectiveHandlerInterface, string}>
     */
    public static function handlerDataProvider(): iterable
    {
        yield 'include' => [new IncludeDirectiveHandler(), 'include'];
        yield 'embed' => [new EmbedDirectiveHandler(), 'embed'];
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function foundNameDataProvider(): iterable
    {
        yield 'quoted' => ['"a.bin"'];
        yield 'angled' => ['<a.bin>'];
    }

    #[DataProvider('foundNameDataProvider')]
    public function testIncludeGivesTheOutputOfTheFoundFile(string $name): void
    {
        $files = new VirtualFileSystem();
        $files->add('a.bin', VirtualSource::createFromString('a.bin', 'AB'));
        $listener = new RecordingDiagnosticListener();
        $includer = new RecordingSourceExecutor();

        $chunks = [...(new IncludeDirectiveHandler())->handle(
            self::readDirective("#include $name\n"),
            self::createContext($listener, null, null, $files, $includer),
        )];

        self::assertCount(1, $chunks);
        self::assertSame('AB', $chunks[0]->content);
        self::assertCount(1, $includer->files);
        self::assertSame('/a.bin', $includer->files[0]->pathname);
        self::assertSame([], $listener->diagnostics);
    }

    #[DataProvider('handlerDataProvider')]
    public function testNameFromMacroReplacementIsTheSameAsWrittenOne(
        DirectiveHandlerInterface $handler,
        string $name,
    ): void {
        $files = new VirtualFileSystem();
        $files->add('a.h', VirtualSource::createFromString('a.h', 'AB'));
        $macros = new MacroRepository();
        $macros->defineByString('HEADER', '"a.h"');
        $listener = new RecordingDiagnosticListener();

        $chunks = [...$handler->handle(
            self::readDirective("#$name HEADER\n"),
            self::createContext($listener, null, $macros, $files),
        )];

        self::assertCount(1, $chunks);
        self::assertSame([], $listener->diagnostics);
    }

    public function testMacroReplacementThatIsNotHeaderNameIsRejected(): void
    {
        $macros = new MacroRepository();
        $macros->defineByString('HEADER', '"a.h" X');

        $this->expectException(InvalidDirectiveBodyException::class);
        $this->expectExceptionMessage('#include expects "FILENAME" or <FILENAME>');

        [...(new IncludeDirectiveHandler())->handle(
            self::readDirective("#include HEADER\n"),
            self::createContext(new RecordingDiagnosticListener(), null, $macros),
        )];
    }

    #[DataProvider('foundNameDataProvider')]
    public function testEmbedGivesTheBytesOfTheFoundFile(string $name): void
    {
        $files = new VirtualFileSystem();
        $files->add('a.bin', VirtualSource::createFromString('a.bin', 'AB'));

        $chunks = [...(new EmbedDirectiveHandler())->handle(
            self::readDirective("#embed $name\n"),
            self::createContext(new RecordingDiagnosticListener(), null, null, $files),
        )];

        self::assertCount(1, $chunks);
        self::assertSame('65,66', $chunks[0]->content);
    }

    public function testEmbedOfEmptyFileGivesNothing(): void
    {
        $files = new VirtualFileSystem();
        $files->add('a.bin', VirtualSource::createFromString('a.bin', ''));

        $chunks = [...(new EmbedDirectiveHandler())->handle(
            self::readDirective("#embed \"a.bin\"\n"),
            self::createContext(new RecordingDiagnosticListener(), null, null, $files),
        )];

        self::assertSame([], $chunks);
    }

    #[DataProvider('handlerDataProvider')]
    public function testMissingFileIsReportedAsError(DirectiveHandlerInterface $handler, string $name): void
    {
        $listener = new RecordingDiagnosticListener();

        $chunks = [...$handler->handle(
            self::readDirective("#$name \"nope.h\"\n"),
            self::createContext($listener),
        )];

        self::assertSame([], $chunks);
        self::assertCount(1, $listener->diagnostics);
        self::assertSame(Severity::Error, $listener->diagnostics[0]->severity);
        self::assertSame("#$name: file \"nope.h\" is not found", $listener->diagnostics[0]->message);
    }

    #[DataProvider('handlerDataProvider')]
    public function testExtraTokensAfterTheNameAreWarnedAbout(DirectiveHandlerInterface $handler, string $name): void
    {
        $files = new VirtualFileSystem();
        $files->add('a.h', VirtualSource::createFromString('a.h', ''));
        $listener = new RecordingDiagnosticListener();

        [...$handler->handle(
            self::readDirective("#$name \"a.h\" X\n"),
            self::createContext($listener, null, null, $files),
        )];

        self::assertCount(1, $listener->diagnostics);
        self::assertSame(Severity::Warning, $listener->diagnostics[0]->severity);
    }

    #[DataProvider('handlerDataProvider')]
    public function testNameThatIsNotHeaderNameIsRejected(DirectiveHandlerInterface $handler, string $name): void
    {
        $this->expectException(InvalidDirectiveBodyException::class);
        $this->expectExceptionMessage("#$name expects \"FILENAME\" or <FILENAME>");

        [...$handler->handle(
            self::readDirective("#$name MACRO\n"),
            self::createContext(new RecordingDiagnosticListener()),
        )];
    }
}
