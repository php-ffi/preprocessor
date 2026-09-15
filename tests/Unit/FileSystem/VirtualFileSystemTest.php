<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\FileSystem;

use FFI\Preprocessor\FileSystem\Exception\DirectoryNotFoundException;
use FFI\Preprocessor\FileSystem\Exception\InvalidPathException;
use FFI\Preprocessor\FileSystem\PathMatcher\UnixPathMatcher;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(VirtualFileSystem::class)]
final class VirtualFileSystemTest extends TestCase
{
    public function testEmptyFileSystemFindsNothing(): void
    {
        $files = new VirtualFileSystem();

        self::assertNull($files->find('a.h'));
        self::assertNull($files->find(''));
        self::assertNull($files->find('/'));
        self::assertNull($files->find('a.h', self::createFile('', 'main.h')));
    }

    public function testFileIsFoundUnderItsVirtualPathname(): void
    {
        $files = new VirtualFileSystem();
        $files->addFile('sys/a.h', new StringSource('a'));

        $file = $files->find('sys/a.h');

        self::assertNotNull($file);
        self::assertSame('/sys/a.h', $file->pathname);
        self::assertSame('a', $file->read(0, 10));
    }

    /**
     * @return iterable<non-empty-string, array{string, string, non-empty-string}>
     */
    public static function equivalentPathnameDataProvider(): iterable
    {
        yield 'relative and absolute' => ['a.h', '/a.h', '/a.h'];
        yield 'absolute and relative' => ['/a.h', 'a.h', '/a.h'];
        yield 'backslashes' => ['sys\\a.h', 'sys/a.h', '/sys/a.h'];
        yield 'current directory' => ['./sys/./a.h', 'sys/a.h', '/sys/a.h'];
        yield 'parent directory' => ['sys/../sys/a.h', 'sys/a.h', '/sys/a.h'];
        yield 'parent of the root' => ['../../sys/a.h', 'sys/a.h', '/sys/a.h'];
        yield 'repeated slashes' => ['sys//a.h', 'sys/a.h', '/sys/a.h'];
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('equivalentPathnameDataProvider')]
    public function testFileIsFoundByEquivalentPathname(string $added, string $searched, string $expected): void
    {
        $files = new VirtualFileSystem();
        $files->addFile($added, new StringSource('a'));

        self::assertSame($expected, $files->find($searched)?->pathname);
    }

    public function testVirtualPathnameIsCaseSensitive(): void
    {
        $files = new VirtualFileSystem();
        $files->addFile('a.h', new StringSource('a'));

        self::assertNull($files->find('A.h'));
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function rootPathnameDataProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'slash' => ['/'];
        yield 'current directory' => ['.'];
        yield 'parent of the root' => ['../..'];
    }

    #[DataProvider('rootPathnameDataProvider')]
    public function testFileCannotBeAddedAtRoot(string $pathname): void
    {
        $this->expectException(InvalidPathException::class);
        $this->expectExceptionMessage(\sprintf('The file pathname "%s" names the root directory', $pathname));

        (new VirtualFileSystem())->addFile($pathname, new StringSource('a'));
    }

    public function testMissingDirectoryCannotBeMounted(): void
    {
        $directory = $this->createDirectory() . '/missing';

        $this->expectException(DirectoryNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('The directory "%s" does not exist', $directory));

        (new VirtualFileSystem())->addDirectory($directory);
    }

    public function testFileCannotBeMountedAsDirectory(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);

        $this->expectException(DirectoryNotFoundException::class);

        (new VirtualFileSystem())->addDirectory($directory . '/a.h');
    }

    public function testEmptyDirectoryPathnameCannotBeMounted(): void
    {
        $this->expectException(DirectoryNotFoundException::class);
        $this->expectExceptionMessage('The directory "" does not exist');

        (new VirtualFileSystem())->addDirectory('');
    }

    public function testFailedMountingKeepsFileSystem(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);
        $files = new VirtualFileSystem();
        $files->addDirectory($directory);

        try {
            $files->addDirectory($directory . '/missing');
        } catch (DirectoryNotFoundException) {
        }

        self::assertSame('a', $files->find('a.h')?->read(0, 10));
    }

    public function testFileOfDirectoryMountedToRootIsFound(): void
    {
        $directory = $this->createDirectory(['sys/a.h' => 'a']);

        $files = new VirtualFileSystem();
        $files->addDirectory($directory);

        self::assertSame('/sys/a.h', $files->find('sys/a.h')?->pathname);
    }

    public function testFileOfMountedDirectoryIsFoundUnderVirtualPathname(): void
    {
        $directory = $this->createDirectory(['sys/a.h' => 'a']);

        $files = new VirtualFileSystem();
        $files->addDirectory($directory, 'sdk');

        $file = $files->find('sdk/sys/a.h');

        self::assertNotNull($file);
        self::assertSame('/sdk/sys/a.h', $file->pathname);
        self::assertSame('a', $file->read(0, 10));
        self::assertNull($files->find('sys/a.h'));
    }

    public function testMountedDirectoriesAreSearchedInOrderOfMounting(): void
    {
        $first = $this->createDirectory(['a.h' => 'first']);
        $second = $this->createDirectory(['a.h' => 'second', 'b.h' => 'second']);

        $files = new VirtualFileSystem();
        $files->addDirectory($first);
        $files->addDirectory($second);

        self::assertSame('first', $files->find('a.h')?->read(0, 10));
        self::assertSame('second', $files->find('b.h')?->read(0, 10));
    }

    public function testFileHidesFileOfMountedDirectory(): void
    {
        $directory = $this->createDirectory(['a.h' => 'directory']);

        $files = new VirtualFileSystem();
        $files->addDirectory($directory);
        $files->addFile('a.h', new StringSource('file'));

        self::assertSame('file', $files->find('a.h')?->read(0, 10));
    }

    public function testFileReplacesFileOfSamePathname(): void
    {
        $files = new VirtualFileSystem();
        $files->addFile('a.h', new StringSource('first'));
        $files->addFile('/a.h', new StringSource('second'));

        self::assertSame('second', $files->find('a.h')?->read(0, 10));
    }

    public function testParentOfRootDoesNotLeaveMountedDirectory(): void
    {
        $outside = $this->createDirectory(['secret.h' => 'secret']);
        $inside = $this->createDirectory(['a.h' => '']);
        $relative = '../' . \basename($outside) . '/secret.h';

        $files = new VirtualFileSystem();
        $files->addDirectory($inside);

        $from = $files->find('a.h');

        self::assertNotNull($from);
        self::assertFileExists($inside . '/' . $relative);
        self::assertNull($files->find($relative));
        self::assertNull($files->find($relative, $from));
    }

    public function testQuotedNameIsSearchedInDirectoryOfIncludingFileFirst(): void
    {
        $files = new VirtualFileSystem();
        $files->addFile('a.h', new StringSource('root'));
        $files->addFile('sys/a.h', new StringSource('sys'));

        $from = self::createFile('', 'sys/b.h');

        self::assertSame('/sys/a.h', $files->find('a.h', $from)?->pathname);
        self::assertSame('/a.h', $files->find('a.h')?->pathname);
    }

    public function testNameRelativeToIncludingFileIsNotSearchedFromRoot(): void
    {
        $files = new VirtualFileSystem();
        $files->addFile('a.h', new StringSource('root'));

        self::assertNull($files->find('a.h', self::createFile('', 'sys/b.h')));
    }

    public function testQuotedNameIsSearchedRelativeToDirectoryOfIncludingFile(): void
    {
        $files = new VirtualFileSystem();
        $files->addFile('lib/a.h', new StringSource('a'));

        $from = self::createFile('', '/lib/sys/b.h');

        self::assertSame('/lib/a.h', $files->find('../a.h', $from)?->pathname);
        self::assertNull($files->find('../a.h'));
    }

    public function testDirectoryOfIncludingFileIsSearchedInAllMountedDirectories(): void
    {
        $first = $this->createDirectory(['sys/b.h' => '']);
        $second = $this->createDirectory(['sys/a.h' => 'second']);

        $files = new VirtualFileSystem();
        $files->addDirectory($first);
        $files->addDirectory($second);

        $from = $files->find('sys/b.h');

        self::assertNotNull($from);
        self::assertSame('second', $files->find('a.h', $from)?->read(0, 10));
    }

    public function testAbsoluteQuotedNameIsSearchedFromRootOnly(): void
    {
        $files = new VirtualFileSystem();
        $files->addFile('a.h', new StringSource('root'));
        $files->addFile('sys/a.h', new StringSource('sys'));

        $from = self::createFile('', 'sys/b.h');

        self::assertSame('/a.h', $files->find('/a.h', $from)?->pathname);
        self::assertSame('/a.h', $files->find('\\a.h', $from)?->pathname);
    }

    public function testAbsolutePathnameIsToldByGivenMatcher(): void
    {
        $files = new VirtualFileSystem(matcher: new UnixPathMatcher());
        $files->addFile('a.h', new StringSource('root'));
        $files->addFile('sys/a.h', new StringSource('sys'));

        $from = self::createFile('', 'sys/b.h');

        self::assertSame('/a.h', $files->find('/a.h', $from)?->pathname);
        self::assertSame('/sys/a.h', $files->find('\\a.h', $from)?->pathname);
    }

    public function testDirectoryIsNotFoundAsFile(): void
    {
        $directory = $this->createDirectory(['sys/a.h' => '']);

        $files = new VirtualFileSystem();
        $files->addDirectory($directory);

        self::assertNull($files->find('sys'));
        self::assertNull($files->find('sys', self::createFile('', 'main.h')));
    }

    public function testParentFileSystemIsSearchedForMissingFile(): void
    {
        $parent = new VirtualFileSystem();
        $parent->addFile('a.h', new StringSource('parent'));
        $parent->addFile('b.h', new StringSource('parent'));

        $files = new VirtualFileSystem(parent: $parent);
        $files->addFile('a.h', new StringSource('child'));

        self::assertSame('child', $files->find('a.h')?->read(0, 10));
        self::assertSame('parent', $files->find('b.h')?->read(0, 10));
        self::assertNull($files->find('c.h'));
    }

    public function testParentFileSystemIsSearchedAfterMountedDirectories(): void
    {
        $directory = $this->createDirectory(['a.h' => 'directory']);

        $parent = new VirtualFileSystem();
        $parent->addFile('a.h', new StringSource('parent'));

        $files = new VirtualFileSystem(parent: $parent);
        $files->addDirectory($directory);

        self::assertSame('directory', $files->find('a.h')?->read(0, 10));
    }

    public function testParentFileSystemIsSearchedInDirectoryOfIncludingFile(): void
    {
        $parent = new VirtualFileSystem();
        $parent->addFile('sys/a.h', new StringSource('parent'));

        $files = new VirtualFileSystem(parent: $parent);
        $files->addFile('a.h', new StringSource('child'));

        self::assertSame('parent', $files->find('a.h', self::createFile('', 'sys/b.h'))?->read(0, 10));
    }

    public function testAddingToFileSystemKeepsParentFileSystem(): void
    {
        $parent = new VirtualFileSystem();

        $files = new VirtualFileSystem(parent: $parent);
        $files->addFile('a.h', new StringSource('a'));

        self::assertNull($parent->find('a.h'));
    }

    public function testParentFileSystemIsSearchedAsItIsAtLookup(): void
    {
        $parent = new VirtualFileSystem();
        $files = new VirtualFileSystem(parent: $parent);

        $parent->addFile('a.h', new StringSource('a'));

        self::assertSame('a', $files->find('a.h')?->read(0, 10));
    }
}
