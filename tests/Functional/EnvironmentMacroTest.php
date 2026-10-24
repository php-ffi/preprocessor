<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Functional;

use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class EnvironmentMacroTest extends TestCase
{
    private static function process(string $content): string
    {
        $system = new VirtualFileSystem();
        $system->add('inc.h', VirtualSource::createFromString('inc.h', "x\n  __FILE__ __LINE__\n"));

        return (string) (new Preprocessor(files: $system))->process($content);
    }

    public function testLineIsTheLineOfTheMacroName(): void
    {
        self::assertSame("a\n2\n\n4\n", self::process("a\n__LINE__\n\n__LINE__\n"));
    }

    public function testFileIsThePathnameOfTheFileTheMacroNameIsWrittenIn(): void
    {
        self::assertSame("\"{entrypoint}\"\nx\n  \"/inc.h\" 2\n", self::process("__FILE__\n#include \"inc.h\"\n"));
    }

    public function testLineOfTheNameInAReplacementListIsTheLineOfTheInvocation(): void
    {
        self::assertSame("5 5\n", self::process("\n\n#define L __LINE__\n#define ID(x) x\nL ID(__LINE__)\n"));
    }

    public function testLineIsKnownToTheControllingExpression(): void
    {
        self::assertSame("yes\n", self::process("#if __LINE__ == 1\nyes\n#endif\n"));
    }
}
