<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem;

interface PathMatcherInterface
{
    /**
     * Whether the pathname is absolute: it is searched from the root, not
     * relative to the directory of the including file.
     */
    public function isAbsolute(string $pathname): bool;
}
