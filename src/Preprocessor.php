<?php

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Diagnostic\DiagnosticListener\ThrowingDiagnosticListener;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use FFI\Preprocessor\Directive\DirectiveRepository;
use FFI\Preprocessor\Directive\DirectiveRepositoryInterface;
use FFI\Preprocessor\Directive\MutableDirectiveRepositoryInterface;
use FFI\Preprocessor\FileSystem\FileSystemInterface;
use FFI\Preprocessor\FileSystem\RealFileSystem;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Macro\MacroRepositoryInterface;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use FFI\Preprocessor\Runtime\ChunkReader;
use FFI\Preprocessor\Runtime\SourceExecutor;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Contracts\Source\SourceFactoryInterface;
use Phplrt\Source\SourceFactory;
use Phplrt\Source\VirtualSource;

/**
 * Preprocesses C sources (5.1.1.2, translation phases 1 to 4).
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 */
final class Preprocessor
{
    /**
     * Creates the source to preprocess from what the caller gives
     */
    private readonly SourceFactoryInterface $sources;

    /**
     * The macros every run starts with, defined over the given ones, a run does not change them
     */
    public readonly MacroRepository $macros;

    /**
     * The handlers of the directives
     */
    public readonly MutableDirectiveRepositoryInterface $directives;

    /**
     * The files `#include` and `#embed` search
     */
    public readonly FileSystemInterface $files;

    /**
     * Receives the messages of `#error` and `#warning`
     */
    public readonly DiagnosticListenerInterface $diagnostic;

    /**
     * Reads the files into the chunks
     */
    private readonly ChunkReader $reader;

    /**
     * Every argument is optional: the one that is not given is the default of the standard.
     */
    public function __construct(
        ?SourceFactoryInterface $sources = null,
        ?MacroRepositoryInterface $macros = null,
        ?DirectiveRepositoryInterface $directives = null,
        ?FileSystemInterface $files = null,
        ?DiagnosticListenerInterface $diagnostic = null,
        ?ChunkReader $reader = null,
        /**
         * The number of the files that may be open at once, the first one included
         *
         * @var int<1, max>
         */
        public readonly int $maxIncludeDepth = 200,
    ) {
        $this->sources = $this->createSourceFactory($sources);
        $this->macros = $this->createMacroRepository($macros);
        $this->directives = $this->createDirectiveRepository($directives);
        $this->files = $this->createFileSystem($files);
        $this->diagnostic = $this->createDiagnosticListener($diagnostic);
        $this->reader = $this->createChunkReader($reader);
    }

    private function createSourceFactory(?SourceFactoryInterface $sources): SourceFactoryInterface
    {
        if ($sources !== null) {
            return $sources;
        }

        return SourceFactory::createDefault();
    }

    private function createMacroRepository(?MacroRepositoryInterface $macros): MacroRepository
    {
        return new MacroRepository($macros);
    }

    private function createDirectiveRepository(?DirectiveRepositoryInterface $directives): MutableDirectiveRepositoryInterface
    {
        if ($directives !== null) {
            return new DirectiveRepository($directives);
        }

        return new DirectiveRepository(DirectiveRepository::createDefault());
    }

    private function createFileSystem(?FileSystemInterface $files): FileSystemInterface
    {
        if ($files !== null) {
            return $files;
        }

        return new RealFileSystem();
    }

    private function createDiagnosticListener(?DiagnosticListenerInterface $diagnostic): DiagnosticListenerInterface
    {
        if ($diagnostic !== null) {
            return $diagnostic;
        }

        return new ThrowingDiagnosticListener();
    }

    private function createChunkReader(?ChunkReader $reader): ChunkReader
    {
        if ($reader !== null) {
            return $reader;
        }

        return ChunkReader::createDefault();
    }

    private function createExecutor(): SourceExecutor
    {
        return new SourceExecutor(
            $this->reader,
            $this->directives,
            new MacroRepository($this->macros),
            $this->files,
            $this->diagnostic,
            $this->maxIncludeDepth,
        );
    }

    /**
     * Preprocesses the source, the output is made while it is iterated.
     *
     * @param mixed $source whatever the source factory creates a source from
     * @throws \Throwable in case of the source cannot be created, read or preprocessed
     */
    public function process(mixed $source): Result
    {
        $readable = $this->sources->create($source);

        if (!$readable instanceof FileInterface) {
            $readable = new VirtualSource('{entrypoint}', $readable);
        }

        $executor = $this->createExecutor();

        return new Result($executor->execute($readable));
    }
}
