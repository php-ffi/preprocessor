<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\FileSystem\Exception;

use FFI\Preprocessor\FileSystem\Exception\InvalidPathException;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidPathException::class)]
final class InvalidPathExceptionTest extends TestCase
{
    public function testRootPathnameIsReported(): void
    {
        $e = InvalidPathException::becausePathIsRoot('/..');

        self::assertSame('The file pathname "/.." names the root directory', $e->getMessage());
        self::assertSame(0, $e->getCode());
        self::assertNull($e->getPrevious());
    }

    public function testQuotesInMessageAreEscaped(): void
    {
        $e = InvalidPathException::becausePathIsRoot('"a"/..');

        self::assertSame('The file pathname "\\"a\\"/.." names the root directory', $e->getMessage());
    }
}
