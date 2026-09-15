<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\FileSystem\PathMatcher;

use FFI\Preprocessor\FileSystem\PathMatcher\CompoundPathMatcher;
use FFI\Preprocessor\FileSystem\PathMatcher\UnixPathMatcher;
use FFI\Preprocessor\FileSystem\PathMatcher\WindowsPathMatcher;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompoundPathMatcher::class)]
final class CompoundPathMatcherTest extends TestCase
{
    public function testPathnameIsAbsoluteForAnyOfMatchers(): void
    {
        $matcher = new CompoundPathMatcher([new UnixPathMatcher(), new WindowsPathMatcher()]);

        self::assertTrue($matcher->isAbsolute('/a.h'));
        self::assertTrue($matcher->isAbsolute('C:\\a.h'));
        self::assertFalse($matcher->isAbsolute('a.h'));
    }

    public function testPathnameIsAbsoluteForTheGivenMatchersOnly(): void
    {
        $matcher = new CompoundPathMatcher([new UnixPathMatcher()]);

        self::assertTrue($matcher->isAbsolute('/a.h'));
        self::assertFalse($matcher->isAbsolute('C:\\a.h'));
    }
}
