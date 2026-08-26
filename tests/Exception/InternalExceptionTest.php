<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Exception;

use FFI\Preprocessor\Exception\InternalException;
use FFI\Preprocessor\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InternalException::class)]
final class InternalExceptionTest extends TestCase
{
    public function testUnreadableSourceKeepsTheReasonOfTheFailure(): void
    {
        $reason = new \RuntimeException('Permission denied');

        $e = InternalException::becauseSourceIsNotReadable(self::createFile('', 'a.h'), $reason);

        self::assertSame('The source "a.h" cannot be read: "Permission denied"', $e->getMessage());
        self::assertSame($reason, $e->getPrevious());
        self::assertSame(0, $e->getCode());
    }

    public function testQuotesInMessageAreEscaped(): void
    {
        $reason = new \RuntimeException('file "a"');

        $e = InternalException::becauseSourceIsNotReadable(self::createFile('', 'dir "x"/a.h'), $reason);

        self::assertSame('The source "dir \\"x\\"/a.h" cannot be read: "file \\"a\\""', $e->getMessage());
    }
}
