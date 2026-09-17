<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Functional;

use FFI\Preprocessor\Diagnostic\DiagnosticListener\NullDiagnosticListener;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Result;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Result::class)]
final class ResultTest extends TestCase
{
    private static function process(string $content): Result
    {
        $system = new VirtualFileSystem();
        $system->addFile('a.h', VirtualSource::createFromString('a.h', "#define A 1\n#warning hi\n"));

        $preprocessor = new Preprocessor(files: $system, diagnostic: new NullDiagnosticListener());
        $preprocessor->macros->define('CONFIG');

        return $preprocessor->process($content);
    }

    public function testMacrosAreReadWithoutIteratingTheOutput(): void
    {
        $result = self::process("#include \"a.h\"\n#undef CONFIG\n");

        self::assertTrue($result->hasMacro('A'));
        self::assertNotNull($result->findMacro('A'));
        self::assertFalse($result->hasMacro('CONFIG'));
        self::assertNull($result->findMacro('CONFIG'));
    }

    public function testMacrosListsTheDefinedMacrosByTheirNames(): void
    {
        $names = \array_keys(\iterator_to_array(self::process("#define A 1\n#define B 2\n")->getMacros()));

        self::assertSame(['CONFIG', 'A', 'B'], $names);
    }

    public function testFilesListsTheEntrypointAndTheIncludedFilesOnce(): void
    {
        $result = self::process("#include \"a.h\"\n#include \"a.h\"\n");

        $pathnames = [];

        foreach ($result->getFiles() as $file) {
            $pathnames[] = $file->pathname;
        }

        self::assertSame(['{entrypoint}', '/a.h'], $pathnames);
    }

    public function testDiagnosticsAreCollected(): void
    {
        self::assertCount(1, self::process("#include \"a.h\"\n")->getDiagnostics());
    }

    public function testOutputIsReadOnceAndCanBeReadAgain(): void
    {
        $result = self::process("#define X 1\nX\n");

        foreach ($result as $_) {
            break;
        }

        self::assertSame("1\n", (string) $result);
        self::assertSame("1\n", (string) $result);
    }
}
