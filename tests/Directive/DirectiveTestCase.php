<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Directive;

use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Stream\Chunk\TaggedChunk\SourceDirective;
use FFI\Preprocessor\Stream\ChunkReader;
use FFI\Preprocessor\Tests\TestCase;

abstract class DirectiveTestCase extends TestCase
{
    /**
     * Reads the first directive of a file of the given content through the
     * stream, so that it is the one the handlers get.
     */
    protected static function readDirective(string $content): SourceDirective
    {
        foreach (ChunkReader::createDefault()->read(self::createFile($content)) as $chunk) {
            if ($chunk instanceof SourceDirective) {
                return $chunk;
            }
        }

        throw new \LogicException('The content has no directive');
    }

    protected static function createContext(RecordingDiagnosticListener $listener, ?OutputStack $output = null): DirectiveContext
    {
        return new DirectiveContext($output ?? new OutputStack(), $listener);
    }
}
