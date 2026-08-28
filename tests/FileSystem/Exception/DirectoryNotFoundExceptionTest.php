<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\FileSystem\Exception;

use FFI\Preprocessor\FileSystem\Exception\DirectoryNotFoundException;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DirectoryNotFoundException::class)]
final class DirectoryNotFoundExceptionTest extends TestCase
{
    public function testMissingDirectoryIsReported(): void
    {
        $e = DirectoryNotFoundException::becauseDirectoryIsNotFound('C:/sdk/include');

        self::assertSame('The directory "C:/sdk/include" does not exist', $e->getMessage());
        self::assertSame(0, $e->getCode());
        self::assertNull($e->getPrevious());
    }

    public function testQuotesInMessageAreEscaped(): void
    {
        $e = DirectoryNotFoundException::becauseDirectoryIsNotFound('/"sdk"');

        self::assertSame('The directory "/\\"sdk\\"" does not exist', $e->getMessage());
    }
}
