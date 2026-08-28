<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

use FFI\Preprocessor\FileSystem\PathMatcher\CompoundPathMatcher;
use FFI\Preprocessor\FileSystem\PathMatcher\UnixPathMatcher;
use FFI\Preprocessor\FileSystem\PathMatcher\WindowsPathMatcher;
use Phplrt\Contracts\Source\FileInterface;

abstract class FileSystem implements FileSystemInterface
{
    protected readonly PathMatcherInterface $matcher;

    /**
     * @param PathMatcherInterface|null $matcher tells the absolute pathnames,
     *        {@see null} for the ones of both Unix and Windows
     */
    public function __construct(?PathMatcherInterface $matcher = null)
    {
        $this->matcher = $matcher ?? $this->createDefaultPathMatcher();
    }

    protected function createDefaultPathMatcher(): PathMatcherInterface
    {
        return new CompoundPathMatcher([
            new UnixPathMatcher(),
            new WindowsPathMatcher(),
        ]);
    }

    protected function isAbsolute(FileInterface|string $file): bool
    {
        if ($file instanceof FileInterface) {
            $file = $file->pathname;
        }

        return $this->matcher->isAbsolute($file);
    }
}
