<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Support;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\FileSystem\FileSystemInterface;
use FFI\Preprocessor\FileSystem\VirtualFileSystem;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
use FFI\Preprocessor\Runtime\ChunkReader;
use FFI\Preprocessor\Runtime\SourceExecutorInterface;
use FFI\Preprocessor\Tests\TestCase;

abstract class DirectiveTestCase extends TestCase
{
    /**
     * Reads the first directive of a file of the given content through the
     * stream, so that it is the one the handlers get.
     */
    protected static function readDirective(string $content): DirectiveChunk
    {
        foreach (ChunkReader::createDefault()->read(self::createFile($content)) as $chunk) {
            if ($chunk instanceof DirectiveChunk) {
                return $chunk;
            }
        }

        throw new \LogicException('The content has no directive');
    }

    protected static function createContext(
        RecordingDiagnosticListener $listener,
        ?OutputStack $output = null,
        ?MacroRepository $macros = null,
        ?FileSystemInterface $files = null,
        ?SourceExecutorInterface $includer = null,
    ): DirectiveContext {
        return new DirectiveContext(
            $output ?? new OutputStack(),
            $listener,
            $macros ?? new MacroRepository(),
            $files ?? new VirtualFileSystem(),
            $includer ?? new RecordingSourceExecutor(),
        );
    }
}
