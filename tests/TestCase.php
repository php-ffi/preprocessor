<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests;

use FFI\Preprocessor\LegacyMacro\ExpansionContextInterface;
use FFI\Preprocessor\Lexer\Token;
use FFI\Preprocessor\Stream\Chunk\SourceMap\SourceMap;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceChunk;
use FFI\Preprocessor\Stream\Chunk\UntaggedChunk;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Real directories made by {@see self::createDirectory()}, removed after the test
     *
     * @var list<non-empty-string>
     */
    private array $directories = [];

    /**
     * Creates a file of the given content under a virtual pathname.
     *
     * @param non-empty-string $pathname
     */
    protected static function createFile(string $content, string $pathname = 'test.h'): FileInterface
    {
        return VirtualSource::createFromString($pathname, $content);
    }

    /**
     * Creates a chunk of the given text without line splices, read from the
     * start of a file of the same content.
     *
     * @param non-empty-string $pathname
     */
    protected static function createChunk(string $content, string $pathname = 'test.h'): SourceChunk
    {
        return new SourceChunk($content, self::createFile($content, $pathname), 0, SourceMap::createEmpty());
    }

    /**
     * Creates an untagged chunk of the given text, read from the given offset
     * of a file of the same content.
     *
     * @param int<0, max> $offset
     */
    protected static function createUntaggedChunk(string $content, int $offset = 0): UntaggedChunk
    {
        return UntaggedChunk::createFromString(self::createFile($content), $content, $offset);
    }

    /**
     * Creates the context of the computed macros: the token stands on the
     * line of the given number in a file of the given name.
     *
     * @param int<1, max> $line
     */
    protected static function createExpansionContext(
        string $fileName = 'test.h',
        int $line = 1,
        string $time = '2026-10-17 12:34:56',
    ): ExpansionContextInterface {
        return new class ($fileName, $line, new \DateTimeImmutable($time)) implements ExpansionContextInterface {
            /**
             * @var array<non-empty-string, int<0, max>>
             */
            private array $counters = [];

            /**
             * @param int<1, max> $line
             */
            public function __construct(
                private readonly string $fileName,
                private readonly int $line,
                private readonly \DateTimeImmutable $time,
            ) {}

            public function getFileName(Token $token): string
            {
                return $this->fileName;
            }

            public function getLine(Token $token): int
            {
                return $this->line;
            }

            public function getTime(): \DateTimeImmutable
            {
                return $this->time;
            }

            public function getIncludeDepth(): int
            {
                return 0;
            }

            public function getRootFileName(): string
            {
                return $this->fileName;
            }

            public function increment(string $counter): int
            {
                $this->counters[$counter] ??= 0;

                return $this->counters[$counter]++;
            }
        };
    }

    /**
     * Creates a real temporary directory of the given files, removed after
     * the test.
     *
     * @param array<non-empty-string, string> $files the contents of the files
     *        by their pathnames relative to the directory
     * @return non-empty-string the real pathname of the directory
     */
    protected function createDirectory(array $files = []): string
    {
        $directory = \sys_get_temp_dir() . '/ffi-preprocessor-' . \bin2hex(\random_bytes(8));
        \mkdir($directory);
        $this->directories[] = $directory;

        foreach ($files as $pathname => $content) {
            $file = $directory . '/' . $pathname;

            if (!\is_dir(\dirname($file))) {
                \mkdir(\dirname($file), 0o777, true);
            }

            \file_put_contents($file, $content);
        }

        return $directory;
    }

    protected function tearDown(): void
    {
        // The files of the directories may still be open by the sources of the test
        \gc_collect_cycles();

        foreach ($this->directories as $directory) {
            self::removeDirectory($directory);
        }

        $this->directories = [];

        parent::tearDown();
    }

    private static function removeDirectory(string $directory): void
    {
        foreach (\scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $pathname = $directory . '/' . $name;

            \is_dir($pathname) ? self::removeDirectory($pathname) : \unlink($pathname);
        }

        \rmdir($directory);
    }
}
