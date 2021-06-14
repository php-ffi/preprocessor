<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Io\Directory;

use FFI\Preprocessor\Io\Normalizer;

final class Repository implements RepositoryInterface, RegistrarInterface
{
    /**
     * @var array<string>
     */
    private array $directories = [];

    /**
     * @var bool
     */
    private bool $optimizationRequired = false;

    /**
     * @param array<string> $directories
     */
    public function __construct(iterable $directories = [])
    {
        foreach ($directories as $directory) {
            $this->include($directory);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function include(string $directory): void
    {
        $this->optimizationRequired = true;
        $this->directories[] = Normalizer::normalize($directory);
    }

    /**
     * {@inheritDoc}
     */
    public function exclude(string $directory): void
    {
        $this->directories = \array_filter($this->directories, $this->filter(
            Normalizer::normalize($directory)
        ));
    }

    /**
     * @param string $directory
     * @return callable(string): bool
     */
    private function filter(string $directory): callable
    {
        return static fn(string $haystack): bool =>
            ! \str_starts_with($haystack, $directory)
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        if ($this->optimizationRequired) {
            $this->optimize();
        }

        return new \ArrayIterator($this->directories);
    }

    /**
     * @return void
     */
    private function optimize(): void
    {
        $this->optimizationRequired = false;
        $this->directories = \array_unique($this->directories);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->directories);
    }
}
