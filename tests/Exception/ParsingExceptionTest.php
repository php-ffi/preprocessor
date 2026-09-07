<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Exception;

use FFI\Preprocessor\Exception\ParsingException;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Source\FileSource;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ParsingException::class)]
final class ParsingExceptionTest extends TestCase
{
    /**
     * @param int<0, max> $offset
     * @param int<0, max> $length
     */
    private static function createException(ReadableInterface $source, int $offset, int $length = 0): ParsingException
    {
        return new class ('Unexpected "@"', $source, $offset, $length) extends ParsingException {
            /**
             * @param int<0, max> $offset
             * @param int<0, max> $length
             */
            public function __construct(string $message, ReadableInterface $source, int $offset, int $length)
            {
                parent::__construct($message, $source, $offset, $length);
            }
        };
    }

    public function testFailureKnowsItsPlace(): void
    {
        $source = new StringSource("int a;\nint @;\n");

        $e = self::createException($source, 11, 1);

        self::assertSame('Unexpected "@"', $e->getMessage());
        self::assertSame($source, $e->source);
        self::assertSame(11, $e->offset);
        self::assertSame(1, $e->length);
        self::assertSame(0, $e->getCode());
    }

    public function testFailureOfFileIsPrintedWithItsPathnameAndSnippet(): void
    {
        $printed = (string) self::createException(self::createFile("int a;\nint @;\n", 'include/a.h'), 11, 1);

        self::assertStringContainsString('Unexpected "@"', $printed);
        self::assertStringContainsString('include/a.h:2:5', $printed);
        self::assertStringContainsString('int @;', $printed);
    }

    public function testFailureOfTextIsPrintedWithSnippet(): void
    {
        $printed = (string) self::createException(new StringSource("int a;\nint @;\n"), 11, 1);

        self::assertStringContainsString('Unexpected "@"', $printed);
        self::assertStringContainsString('int @;', $printed);
    }

    public function testFailureOfUnreadableSourceIsPrintedWithoutSnippet(): void
    {
        $printed = (string) self::createException(new FileSource(__DIR__ . '/missing-file.h'), 0);

        self::assertStringContainsString('Unexpected "@"', $printed);
        self::assertStringNotContainsString('missing-file.h:', $printed);
    }
}
