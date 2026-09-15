<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\FileSystem\PathMatcher;

use FFI\Preprocessor\FileSystem\PathMatcher\WindowsPathMatcher;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(WindowsPathMatcher::class)]
final class WindowsPathMatcherTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{string, bool}>
     */
    public static function pathnameDataProvider(): iterable
    {
        yield 'drive and backslash' => ['C:\\sdk\\a.h', true];
        yield 'drive and slash' => ['c:/sdk/a.h', true];
        yield 'drive without separator' => ['C:a.h', true];
        yield 'backslash' => ['\\a.h', true];
        yield 'slash' => ['/a.h', true];
        yield 'network share' => ['\\\\server\\share\\a.h', true];
        yield 'empty' => ['', false];
        yield 'name' => ['a.h', false];
        yield 'current directory' => ['.\\a.h', false];
        yield 'parent directory' => ['..\\a.h', false];
        yield 'colon in a name' => ['ab:/a.h', false];
        yield 'digit before colon' => ['1:/a.h', false];
    }

    #[DataProvider('pathnameDataProvider')]
    public function testAbsolutePathnameStartsWithSeparatorOrDrive(string $pathname, bool $expected): void
    {
        self::assertSame($expected, (new WindowsPathMatcher())->isAbsolute($pathname));
    }
}
