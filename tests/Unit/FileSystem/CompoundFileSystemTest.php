<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Unit\FileSystem;

use FFI\Preprocessor\FileSystem\CompoundFileSystem;
use FFI\Preprocessor\FileSystem\RealFileSystem;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Source\FileSource;
use Phplrt\Source\StringSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompoundFileSystem::class)]
final class CompoundFileSystemTest extends TestCase
{
    public function testFileSystemsAreSearchedInTheirOrder(): void
    {
        $first = new VirtualFileSystem();
        $first->add('a.h', new StringSource('first'));

        $second = new VirtualFileSystem();
        $second->add('a.h', new StringSource('second'));
        $second->add('b.h', new StringSource('second'));

        $files = new CompoundFileSystem([$first, $second]);

        self::assertSame('first', $files->find('a.h')?->read(0, 10));
        self::assertSame('second', $files->find('b.h')?->read(0, 10));
        self::assertNull($files->find('c.h'));
    }

    public function testIncludingFileIsGivenToEachFileSystem(): void
    {
        $directory = $this->createDirectory(['main.h' => '', 'near.h' => 'near']);

        $virtual = new VirtualFileSystem();
        $virtual->add('sys/a.h', new StringSource('virtual'));

        $files = new CompoundFileSystem([$virtual, new RealFileSystem()]);

        self::assertSame('virtual', $files->find('a.h', self::createFile('', 'sys/b.h'))?->read(0, 10));
        self::assertSame('near', $files->find('near.h', new FileSource($directory . '/main.h'))?->read(0, 10));
    }

    public function testFileSystemsAreSearchedAsTheyAreAtLookup(): void
    {
        $virtual = new VirtualFileSystem();
        $files = new CompoundFileSystem([$virtual]);

        $virtual->add('a.h', new StringSource('a'));

        self::assertSame('a', $files->find('a.h')?->read(0, 10));
    }
}
