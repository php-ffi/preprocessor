<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Io\Source;

use FFI\Preprocessor\Io\Normalizer;
use FFI\Preprocessor\PreprocessorInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Source\File;

/**
 * @psalm-import-type SourceEntry from PreprocessorInterface
 * @link PreprocessorInterface
 */
final class Repository implements RepositoryInterface, RegistrarInterface
{
    /**
     * @var array<string, ReadableInterface>
     */
    private array $files = [];

    /**
     * @param iterable<string, SourceEntry> $files
     */
    public function __construct(iterable $files = [])
    {
        foreach ($files as $file => $source) {
            $this->add($file, $source);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function add(string $file, mixed $source, bool $overwrite = false): bool
    {
        $file = Normalizer::normalize($file);

        if ($overwrite === false && isset($this->files[$file])) {
            return false;
        }

        $this->files[$file] = File::new($source);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $file): bool
    {
        $file = Normalizer::normalize($file);

        $exists = isset($this->files[$file]);

        unset($this->files[$file]);

        return $exists;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->files);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->files);
    }
}
