<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Io;

use FFI\Contracts\Preprocessor\Io\Directory\RegistrarInterface;
use FFI\Contracts\Preprocessor\Io\Directory\RepositoryInterface;

/**
 * @template-implements \IteratorAggregate<array-key, non-empty-string>
 */
final class DirectoriesRepository implements RepositoryInterface, RegistrarInterface, \IteratorAggregate
{
    /**
     * @var list<non-empty-string>
     */
    private array $directories = [];

    /**
     * @var bool
     */
    private bool $optimizationRequired = false;

    /**
     * @param iterable<non-empty-string> $directories
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

        $directory = Normalizer::normalize($directory);

        if ($directory === '') {
            throw new \InvalidArgumentException('Directory must not be empty');
        }

        $this->directories[] = $directory;
    }

    /**
     * {@inheritDoc}
     */
    public function exclude(string $directory): void
    {
        $filter = static fn(string $haystack): bool =>
            ! \str_starts_with($haystack, Normalizer::normalize($directory))
        ;

        /** @psalm-suppress PropertyTypeCoercion */
        $this->directories = \array_filter($this->directories, $filter);
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

        /** @psalm-suppress PropertyTypeCoercion */
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
