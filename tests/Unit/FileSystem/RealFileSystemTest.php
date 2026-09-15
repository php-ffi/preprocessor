<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\FileSystem;

use FFI\Preprocessor\FileSystem\PathMatcherInterface;
use FFI\Preprocessor\FileSystem\RealFileSystem;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\FileSource;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RealFileSystem::class)]
final class RealFileSystemTest extends TestCase
{
    public function testFileIsFoundByAbsolutePathname(): void
    {
        $directory = $this->createDirectory(['sys/a.h' => 'a']);

        $file = (new RealFileSystem())->find($directory . '/sys/a.h');

        self::assertInstanceOf(FileSource::class, $file);
        self::assertSame(\realpath($directory . '/sys/a.h'), $file->pathname);
        self::assertSame('a', $file->read(0, 10));
    }

    public function testFileIsFoundRelativeToDirectoryOfIncludingFile(): void
    {
        $directory = $this->createDirectory(['src/main.h' => '', 'src/sys/a.h' => 'a', 'b.h' => 'b']);
        $files = new RealFileSystem();
        $from = new FileSource($directory . '/src/main.h');

        self::assertSame('a', $files->find('sys/a.h', $from)?->read(0, 10));
        self::assertSame('b', $files->find('../b.h', $from)?->read(0, 10));
        self::assertNull($files->find('b.h', $from));
    }

    public function testRelativePathnameWithoutIncludingFileFindsNothing(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);

        self::assertFileExists($directory . '/a.h');
        self::assertNull((new RealFileSystem())->find('a.h'));
    }

    public function testRelativePathnameIsNotSearchedNextToVirtualFile(): void
    {
        $directory = $this->createDirectory(['main.h' => '', 'a.h' => 'a']);

        $from = self::createFile('', $directory . '/main.h');

        self::assertNull((new RealFileSystem())->find('a.h', $from));
    }

    public function testAbsolutePathnameIsToldByGivenMatcher(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);
        $matcher = new class implements PathMatcherInterface {
            public function isAbsolute(string $pathname): bool
            {
                return false;
            }
        };

        self::assertNull((new RealFileSystem(matcher: $matcher))->find($directory . '/a.h'));
        self::assertSame('a', (new RealFileSystem())->find($directory . '/a.h')?->read(0, 10));
    }

    public function testDirectoryIsNotFoundAsFile(): void
    {
        $directory = $this->createDirectory(['sys/a.h' => 'a']);

        self::assertNull((new RealFileSystem())->find($directory . '/sys'));
    }

    public function testParentFileSystemIsSearchedForMissingFile(): void
    {
        $directory = $this->createDirectory(['a.h' => 'real']);

        $parent = new VirtualFileSystem();
        $parent->addFile('a.h', new StringSource('parent'));
        $parent->addFile('b.h', new StringSource('parent'));

        $files = new RealFileSystem($parent);
        $from = new FileSource($directory . '/a.h');

        self::assertSame('real', $files->find('a.h', $from)?->read(0, 10));
        self::assertSame('parent', $files->find('b.h')?->read(0, 10));
        self::assertNull($files->find('c.h'));
    }

    public function testMissingFileIsNotFound(): void
    {
        $directory = $this->createDirectory();

        self::assertNull((new RealFileSystem())->find($directory . '/missing.h'));
        self::assertNull((new RealFileSystem())->find(''));
    }
}
