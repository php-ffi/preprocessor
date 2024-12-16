<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests;

use FFI\Preprocessor\Io\DirectoriesRepository;

class IncludeDirectoriesTest extends TestCase
{
    public function testDefaultState(): void
    {
        $includes = new DirectoriesRepository();

        $this->assertCount(0, $includes);
        $this->assertSame([], \iterator_to_array($includes));
    }

    public function testAddition(): void
    {
        $includes = new DirectoriesRepository();

        $includes->include(__DIR__);
        $this->assertCount(1, $includes);
        $this->assertSame($this->normalize([__DIR__]), \iterator_to_array($includes));
    }

    /**
     * @param string[] $directories
     *
     * @return string[]
     */
    private function normalize(iterable $directories): array
    {
        $result = [];

        foreach ($directories as $directory) {
            $result[] = \str_replace(['\\', '/'], \DIRECTORY_SEPARATOR, $directory);
        }

        return $result;
    }

    public function testRemoving(): void
    {
        $includes = new DirectoriesRepository();

        $includes->include(__DIR__);
        $this->assertCount(1, $includes);

        $includes->exclude(__DIR__);
        $this->assertCount(0, $includes);
    }

    public function testParentRemoving(): void
    {
        $includes = new DirectoriesRepository();

        // Add 2 directories
        $includes->include(__DIR__);
        $includes->include(\realpath(__DIR__ . '/../src'));
        $this->assertCount(2, $includes);

        // Remove both
        $includes->exclude(\dirname(__DIR__));
        $this->assertCount(0, $includes);
    }

    public function testInitialization(): void
    {
        $includes = new DirectoriesRepository([__DIR__]);

        $this->assertCount(1, $includes);
        $this->assertSame($this->normalize([__DIR__]), \iterator_to_array($includes));
    }
}
