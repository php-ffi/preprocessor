<?php

declare(strict_types=1);

namespace FFI\Preprocessor\FileSystem\PathMatcher;

use FFI\Preprocessor\FileSystem\PathMatcherInterface;

/**
 * Pathname of several systems at once.
 *
 * A pathname is absolute if it is absolute for any of them.
 */
final class CompoundPathMatcher implements PathMatcherInterface
{
    public function __construct(
        /**
         * @var non-empty-list<PathMatcherInterface>
         */
        private readonly array $matchers,
    ) {}

    public function isAbsolute(string $pathname): bool
    {
        if ($pathname === '') {
            return false;
        }

        foreach ($this->matchers as $matcher) {
            if ($matcher->isAbsolute($pathname)) {
                return true;
            }
        }

        return false;
    }
}
