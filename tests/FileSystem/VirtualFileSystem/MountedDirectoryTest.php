<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\FileSystem\VirtualFileSystem;

use FFI\Preprocessor\FileSystem\VirtualFileSystem\MountedDirectory;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\FileSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MountedDirectory::class)]
final class MountedDirectoryTest extends TestCase
{
    public function testFileOfDirectoryMountedToRootIsFound(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);

        $source = (new MountedDirectory('/', $directory))->find('/a.h');

        self::assertInstanceOf(FileSource::class, $source);
        self::assertSame($directory . '/a.h', $source->pathname);
        self::assertSame('a', $source->read(0, 10));
    }

    public function testFileOfSubdirectoryIsFound(): void
    {
        $directory = $this->createDirectory(['sys/a.h' => 'a']);

        $source = (new MountedDirectory('/', $directory))->find('/sys/a.h');

        self::assertInstanceOf(FileSource::class, $source);
        self::assertSame('a', $source->read(0, 10));
    }

    public function testFileOfDirectoryMountedToSubdirectoryIsFoundUnderIt(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);
        $mounted = new MountedDirectory('/sdk/include', $directory);

        self::assertInstanceOf(FileSource::class, $mounted->find('/sdk/include/a.h'));
        self::assertNull($mounted->find('/a.h'));
        self::assertNull($mounted->find('/sdk/a.h'));
    }

    public function testDirectoryWithSamePrefixIsNotMountPoint(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);

        self::assertNull((new MountedDirectory('/sdk', $directory))->find('/sdkx/a.h'));
    }

    public function testMissingFileIsNotFound(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);

        self::assertNull((new MountedDirectory('/', $directory))->find('/b.h'));
    }

    public function testDirectoryIsNotFile(): void
    {
        $directory = $this->createDirectory(['sys/a.h' => 'a']);
        $mounted = new MountedDirectory('/sdk', $directory);

        self::assertNull($mounted->find('/sdk/sys'));
        self::assertNull($mounted->find('/sdk'));
        self::assertNull((new MountedDirectory('/', $directory))->find('/'));
    }

    public function testTrailingSeparatorsOfRealDirectoryAreIgnored(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);

        $source = (new MountedDirectory('/', $directory . '/\\'))->find('/a.h');

        self::assertInstanceOf(FileSource::class, $source);
        self::assertSame($directory . '/a.h', $source->pathname);
    }

    public function testFileCreatedAfterMountingIsFound(): void
    {
        $directory = $this->createDirectory();
        $mounted = new MountedDirectory('/', $directory);

        \file_put_contents($directory . '/a.h', 'a');

        self::assertInstanceOf(FileSource::class, $mounted->find('/a.h'));
    }

    public function testCaseSensitivityIsTheOneOfRealFileSystem(): void
    {
        $directory = $this->createDirectory(['a.h' => 'a']);
        $isCaseInsensitive = \in_array(\PHP_OS_FAMILY, ['Windows', 'Darwin'], true);

        $source = (new MountedDirectory('/', $directory))->find('/A.H');

        self::assertSame($isCaseInsensitive, $source !== null);
    }
}
