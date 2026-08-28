<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\FileSystem\PathMatcher;

use FFI\Preprocessor\FileSystem\PathMatcher\UnixPathMatcher;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(UnixPathMatcher::class)]
final class UnixPathMatcherTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{string, bool}>
     */
    public static function pathnameDataProvider(): iterable
    {
        yield 'slash' => ['/usr/include/a.h', true];
        yield 'root' => ['/', true];
        yield 'empty' => ['', false];
        yield 'name' => ['a.h', false];
        yield 'current directory' => ['./a.h', false];
        yield 'parent directory' => ['../a.h', false];
        yield 'backslash' => ['\\a.h', false];
        yield 'windows drive' => ['C:\\a.h', false];
    }

    #[DataProvider('pathnameDataProvider')]
    public function testAbsolutePathnameStartsWithSlash(string $pathname, bool $expected): void
    {
        self::assertSame($expected, (new UnixPathMatcher())->isAbsolute($pathname));
    }
}
