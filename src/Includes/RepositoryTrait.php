<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Includes;

use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Source\File;

/**
 * @mixin RepositoryProviderInterface
 * @psalm-import-type SourceEntry from RepositoryProviderInterface
 * @internal
 */
trait RepositoryTrait
{
    /**
     * @var string[]
     */
    protected array $includes = [];

    /**
     * @psalm-var array<string, ReadableInterface>
     * @var mixed[]
     */
    protected array $files = [];

    /**
     * {@inheritDoc}
     */
    public function include(string $directory): void
    {
        $this->includes[] = $this->normalizeDirectory($directory);
    }

    /**
     * @param string $directory
     */
    public function exclude(string $directory): void
    {
        $directory = $this->normalizeDirectory($directory);

        $filter = static fn(string $haystack): bool => ! \str_starts_with($haystack, $directory);

        $this->includes = \array_filter($this->includes, $filter);
    }

    /**
     * {@inheritDoc}
     */
    public function add($source, string $name = null): void
    {
        $source = File::new($source);

        if ($name === null && ! $source instanceof FileInterface) {
            throw new \InvalidArgumentException('Name argument is required for non-physical source');
        }

        $name = $this->normalizeDirectory($name ?? \basename($source->getPathname()));

        $this->files[$name] = File::new($source);
    }

    /**
     * @param string $alias
     */
    public function remove(string $alias): void
    {
        unset($this->files[$this->normalizeDirectory($alias)]);
    }

    /**
     * @param string $directory
     * @return string
     */
    private function normalizeDirectory(string $directory): string
    {
        return \rtrim(\str_replace('\\', '/', $directory), '/');
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludedDirectories(): iterable
    {
        return $this->includes;
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludedFiles(): iterable
    {
        return $this->files;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->includes);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->includes);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->includes;
    }
}
