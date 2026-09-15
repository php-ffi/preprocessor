<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests;

use FFI\Preprocessor\Runtime\Chunk\SourceMap\SourceMap;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\SourceChunk;
use FFI\Preprocessor\Runtime\Chunk\UntaggedChunk;
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
