<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Directives\RepositoryProviderInterface as DirectivesRepositoryInterface;
use FFI\Preprocessor\Includes\RepositoryProviderInterface as IncludeDirectoriesRepositoryInterface;

/**
 * @internal
 */
final class Result implements ResultInterface
{
    /**
     * @var \Traversable|string[]
     */
    private \Traversable $stream;

    /**
     * @var DirectivesRepositoryInterface
     */
    private DirectivesRepositoryInterface $directives;

    /**
     * @var IncludeDirectoriesRepositoryInterface
     */
    private IncludeDirectoriesRepositoryInterface $directories;

    /**
     * @var int
     */
    private int $config;

    /**
     * @param \Traversable $stream
     * @param int $config
     * @param DirectivesRepositoryInterface $directives
     * @param IncludeDirectoriesRepositoryInterface $directories
     */
    public function __construct(
        \Traversable $stream,
        DirectivesRepositoryInterface $directives,
        IncludeDirectoriesRepositoryInterface $directories,
        int $config = 0
    ) {
        $this->config = $config;
        $this->stream = $stream;
        $this->directives = $directives;
        $this->directories = $directories;
    }

    /**
     * {@inheritDoc}
     */
    public function getDirectives(): DirectivesRepositoryInterface
    {
        return $this->directives;
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludeDirectories(): IncludeDirectoriesRepositoryInterface
    {
        return $this->directories;
    }

    /**
     * @return \Traversable|string[]
     */
    public function getIterator(): \Traversable
    {
        yield from $this->stream;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $result = '';

        foreach ($this as $chunk) {
            $result .= $chunk;
        }

        if (! ($this->config & Config::KEEP_EXTRA_LINE_FEEDS)) {
            $result = \preg_replace('/\n{2,}/ium', "\n", $result);

            return \trim($result, "\n");
        }

        return $result;
    }
}
