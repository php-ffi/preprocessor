<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Support;

use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\GeneratedChunk;
use FFI\Preprocessor\Runtime\SourceExecutorInterface;
use Phplrt\Contracts\Source\FileInterface;

final class RecordingSourceExecutor implements SourceExecutorInterface
{
    /**
     * @var list<FileInterface>
     */
    public array $files = [];

    /**
     * @var list<FileInterface>
     */
    public array $skipped = [];

    public function skip(FileInterface $file): void
    {
        $this->skipped[] = $file;
    }

    public function execute(FileInterface $file): iterable
    {
        $this->files[] = $file;

        return [new GeneratedChunk($file->content, $file, 0)];
    }
}
