<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Directive;

use FFI\Preprocessor\Directive\FileIncluderInterface;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\GeneratedChunk;
use Phplrt\Contracts\Source\FileInterface;

final class RecordingFileIncluder implements FileIncluderInterface
{
    /**
     * @var list<FileInterface>
     */
    public array $files = [];

    public function includeFile(FileInterface $file): iterable
    {
        $this->files[] = $file;

        return [new GeneratedChunk($file->content, $file, 0)];
    }
}
