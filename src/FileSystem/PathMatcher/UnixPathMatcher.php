<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\PathMatcher;

use FFI\Preprocessor\FileSystem\PathMatcherInterface;

final class UnixPathMatcher implements PathMatcherInterface
{
    public function isAbsolute(string $pathname): bool
    {
        return \str_starts_with($pathname, '/');
    }
}
