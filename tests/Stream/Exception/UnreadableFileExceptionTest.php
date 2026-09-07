<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Stream\Exception;

use FFI\Preprocessor\Stream\Exception\UnreadableFileException;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnreadableFileException::class)]
final class UnreadableFileExceptionTest extends TestCase
{
    public function testUnreadableFileKeepsTheReasonOfTheFailure(): void
    {
        $reason = new \RuntimeException('Permission denied');

        $e = UnreadableFileException::becauseReadFails(self::createFile('', 'a.h'), $reason);

        self::assertSame('The source "a.h" cannot be read: "Permission denied"', $e->getMessage());
        self::assertSame($reason, $e->getPrevious());
        self::assertSame(0, $e->getCode());
    }

    public function testQuotesInMessageAreEscaped(): void
    {
        $reason = new \RuntimeException('file "a"');

        $e = UnreadableFileException::becauseReadFails(self::createFile('', 'dir "x"/a.h'), $reason);

        self::assertSame('The source "dir \\"x\\"/a.h" cannot be read: "file \\"a\\""', $e->getMessage());
    }
}
