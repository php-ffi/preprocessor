<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Io;

use FFI\Contracts\Preprocessor\Io\Source\RegistrarInterface;
use FFI\Contracts\Preprocessor\Io\Source\RepositoryInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Source\File;

/**
 * @template-implements \IteratorAggregate<string, ReadableInterface>
 */
final class SourceRepository implements RepositoryInterface, RegistrarInterface, \IteratorAggregate
{
    /**
     * @var array<string, ReadableInterface>
     */
    private array $files = [];

    /**
     * @param iterable<non-empty-string, string|resource|ReadableInterface|\SplFileInfo> $files
     */
    public function __construct(iterable $files = [])
    {
        foreach ($files as $file => $source) {
            $this->add($file, $source);
        }
    }

    public function add(string $file, $source, bool $overwrite = false): bool
    {
        $file = Normalizer::normalize($file);

        if ($overwrite === false && isset($this->files[$file])) {
            return false;
        }

        $this->files[$file] = File::new($source);

        return true;
    }

    public function remove(string $file): bool
    {
        $file = Normalizer::normalize($file);

        $exists = isset($this->files[$file]);

        unset($this->files[$file]);

        return $exists;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->files);
    }

    public function count(): int
    {
        return \count($this->files);
    }
}
