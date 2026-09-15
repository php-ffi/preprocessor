<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Functional;

use FFI\Preprocessor\Directive\Exception\IncludeDepthException;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\Exception\UnknownDirectiveException;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Runtime\SourceExecutor;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Preprocessor::class)]
#[CoversClass(SourceExecutor::class)]
final class PreprocessorTest extends TestCase
{
    /**
     * @param array<non-empty-string, string> $files the files `#include` finds, by their pathnames
     * @param int<1, max>|null $maxIncludeDepth
     */
    private static function process(string $content, array $files = [], ?int $maxIncludeDepth = null): string
    {
        $system = new VirtualFileSystem();

        foreach ($files as $pathname => $text) {
            $system->addFile($pathname, VirtualSource::createFromString($pathname, $text));
        }

        $preprocessor = new Preprocessor(files: $system, maxIncludeDepth: $maxIncludeDepth ?? 200);

        $output = '';

        foreach ($preprocessor->process($content) as $chunk) {
            $output .= $chunk->content;
        }

        return $output;
    }

    public function testMacrosAreDefinedAndReplacedInTheText(): void
    {
        self::assertSame("int a = 42;\n", self::process("#define X 42\nint a = X;\n"));
    }

    public function testSkippedGroupIsNotPutOut(): void
    {
        $text = "#if 0\nno\n#elif 1\nyes\n#else\nno\n#endif\n";

        self::assertSame("yes\n", self::process($text));
    }

    public function testDirectivesOfSkippedGroupAreNotExecuted(): void
    {
        $text = "#if 0\n#define X 1\n#include <nope.h>\n#unknown\n#endif\nX\n";

        self::assertSame("X\n", self::process($text));
    }

    public function testIncludedFileIsPutOutInPlaceOfTheDirective(): void
    {
        $output = self::process("a\n#include \"b.h\"\nc\n", ['b.h' => "#define Y 1\nb Y\n"]);

        self::assertSame("a\nb 1\nc\n", $output);
    }

    public function testMacroOfIncludedFileIsKnownAfterIt(): void
    {
        $output = self::process("#include <b.h>\nY\n", ['b.h' => "#define Y 7\n"]);

        self::assertSame("7\n", $output);
    }

    public function testConditionalSectionDoesNotCrossTheBoundaryOfTheFile(): void
    {
        $this->expectException(MisplacedDirectiveException::class);
        $this->expectExceptionMessage('#if without #endif');

        self::process("#if 1\n#include <b.h>\n#endif\n", ['b.h' => "#if 1\n"]);
    }

    public function testFileThatIncludesItselfExceedsTheDepthLimit(): void
    {
        $this->expectException(IncludeDepthException::class);

        self::process("#include <a.h>\n", ['a.h' => "#include <a.h>\n"], 5);
    }

    public function testUnknownDirectiveOfSelectedGroupIsRejected(): void
    {
        $this->expectException(UnknownDirectiveException::class);
        $this->expectExceptionMessage('Unknown directive "#defnie"');

        self::process("#defnie X 1\n");
    }

    public function testNullDirectiveIsIgnored(): void
    {
        self::assertSame("a\nb\n", self::process("a\n#\nb\n"));
    }
}
