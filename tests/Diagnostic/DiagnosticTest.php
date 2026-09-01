<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Diagnostic;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Source\Exception\FileNotReadableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Diagnostic::class)]
final class DiagnosticTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{string, int<0, max>, int<1, max>, int<1, max>}>
     */
    public static function positionDataProvider(): iterable
    {
        yield 'start of the file' => ["int a;\n", 0, 1, 1];
        yield 'inside the first line' => ["int a;\n", 4, 1, 5];
        yield 'start of the second line' => ["int a;\n#warning x\n", 7, 2, 1];
        yield 'inside the third line' => ["a\nb\n  #error x\n", 6, 3, 3];
        yield 'after a CRLF line end' => ["a\r\n#warning x\r\n", 3, 2, 1];
    }

    /**
     * @param int<0, max> $offset
     */
    #[DataProvider('positionDataProvider')]
    public function testPositionIsTheLineAndColumnOfTheOffset(string $content, int $offset, int $line, int $column): void
    {
        $diagnostic = new Diagnostic(Severity::Warning, 'text', self::createFile($content), $offset);

        $position = $diagnostic->findPosition();

        self::assertNotNull($position);
        self::assertSame($line, $position->line);
        self::assertSame($column, $position->column);
    }

    public function testPositionOfUnreadableFileIsNotFound(): void
    {
        $file = new class implements FileInterface {
            public readonly string $pathname;

            public function __construct()
            {
                $this->pathname = 'gone.h';
            }

            public function read(int $offset, int $bytes): string
            {
                throw FileNotReadableException::becauseFileNotReadable($this->pathname);
            }
        };

        $diagnostic = new Diagnostic(Severity::Error, 'text', $file, 3);

        self::assertNull($diagnostic->findPosition());
    }

    public function testLengthIsUnknownByDefault(): void
    {
        $diagnostic = new Diagnostic(Severity::Warning, 'text', self::createFile(''), 0);

        self::assertSame(0, $diagnostic->length);
    }
}
