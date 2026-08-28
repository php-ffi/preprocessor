<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\FileSystem\VirtualFileSystem;

use FFI\Preprocessor\FileSystem\VirtualFileSystem\PathNormalizer;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PathNormalizer::class)]
final class PathNormalizerTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{string, non-empty-string}>
     */
    public static function normalizedPathnameDataProvider(): iterable
    {
        yield 'empty' => ['', '/'];
        yield 'root' => ['/', '/'];
        yield 'current directory' => ['.', '/'];
        yield 'relative' => ['a.h', '/a.h'];
        yield 'absolute' => ['/a.h', '/a.h'];
        yield 'nested' => ['a/b/c.h', '/a/b/c.h'];
        yield 'repeated slashes' => ['a//b///c.h', '/a/b/c.h'];
        yield 'trailing slash' => ['a/b/', '/a/b'];
        yield 'current directory names' => ['./a/./b.h', '/a/b.h'];
        yield 'parent directory' => ['a/b/../c.h', '/a/c.h'];
        yield 'parent of the root' => ['../a.h', '/a.h'];
        yield 'parents of the root' => ['/../../a/../../b.h', '/b.h'];
        yield 'backslashes' => ['a\\b\\c.h', '/a/b/c.h'];
        yield 'mixed slashes' => ['a\\/b/\\c.h', '/a/b/c.h'];
        yield 'windows drive' => ['C:\\sdk\\a.h', '/C:/sdk/a.h'];
        yield 'parent of the windows drive' => ['C:\\..\\a.h', '/a.h'];
        yield 'case is kept' => ['Dir/A.H', '/Dir/A.H'];
        yield 'dots in names' => ['a.b/..c/...h', '/a.b/..c/...h'];
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('normalizedPathnameDataProvider')]
    public function testPathnameIsNormalized(string $pathname, string $expected): void
    {
        self::assertSame($expected, PathNormalizer::normalize($pathname));
    }

    /**
     * @return iterable<non-empty-string, array{string, string, non-empty-string}>
     */
    public static function joinedPathnameDataProvider(): iterable
    {
        yield 'root' => ['/', 'a.h', '/a.h'];
        yield 'directory' => ['/a', 'b.h', '/a/b.h'];
        yield 'nested pathname' => ['/a', 'b/c.h', '/a/b/c.h'];
        yield 'parent directory' => ['/a/b', '../c.h', '/a/c.h'];
        yield 'parents of the root' => ['/a', '../../../b.h', '/b.h'];
        yield 'empty pathname' => ['/a', '', '/a'];
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('joinedPathnameDataProvider')]
    public function testPathnameIsJoinedToTheDirectory(string $directory, string $pathname, string $expected): void
    {
        self::assertSame($expected, PathNormalizer::join($directory, $pathname));
    }

    /**
     * @return iterable<non-empty-string, array{string, non-empty-string}>
     */
    public static function directoryDataProvider(): iterable
    {
        yield 'root' => ['/', '/'];
        yield 'empty' => ['', '/'];
        yield 'file at the root' => ['/a.h', '/'];
        yield 'relative file' => ['a.h', '/'];
        yield 'directory at the root' => ['/a', '/'];
        yield 'nested file' => ['/a/b/c.h', '/a/b'];
        yield 'not normalized' => ['a/./b/../c/d.h', '/a/c'];
        yield 'backslashes' => ['a\\b\\c.h', '/a/b'];
        yield 'windows drive' => ['C:\\dir\\file.h', '/C:/dir'];
        yield 'file at the windows drive' => ['C:\\file.h', '/C:'];
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('directoryDataProvider')]
    public function testDirectoryOfThePathnameIsItsParent(string $pathname, string $expected): void
    {
        self::assertSame($expected, PathNormalizer::getDirectory($pathname));
    }
}
