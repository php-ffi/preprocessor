<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Runtime;

use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use FFI\Preprocessor\Directive\DirectiveContext;
use FFI\Preprocessor\Directive\DirectiveContext\OutputStack;
use FFI\Preprocessor\Directive\DirectiveRepositoryInterface;
use FFI\Preprocessor\Directive\Exception\IncludeDepthException;
use FFI\Preprocessor\Directive\Exception\MisplacedDirectiveException;
use FFI\Preprocessor\Directive\Exception\UnknownDirectiveException;
use FFI\Preprocessor\Directive\OutputIndependentDirectiveHandlerInterface;
use FFI\Preprocessor\Exception\PreprocessorException;
use FFI\Preprocessor\FileSystem\FileSystemInterface;
use FFI\Preprocessor\Macro\Exception\MacroInvocationException;
use FFI\Preprocessor\Macro\Executor\MacroExpander;
use FFI\Preprocessor\Macro\MacroRepository;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\DirectiveChunk;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk\GeneratedChunk;
use FFI\Preprocessor\Runtime\Exception\UnclosedCommentException;
use FFI\Preprocessor\Runtime\Exception\UnmatchedTextException;
use Phplrt\Contracts\Source\Exception\SourceExceptionInterface;
use Phplrt\Contracts\Source\FileInterface;

/**
 * Preprocesses the files of one run (5.1.1.2, phase 4): executes the
 * directives, drops the skipped groups and replaces the macros in the text.
 *
 * @link https://www.open-std.org/jtc1/sc22/wg14/www/docs/n3220.pdf C23 (N3220), 5.1.1.2 Translation phases
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal FFI\Preprocessor
 */
final class SourceExecutor implements SourceExecutorInterface
{
    private readonly MacroExpander $expander;

    /**
     * The directive being executed
     */
    private ?DirectiveChunk $current = null;

    /**
     * The number of the files open at the moment
     *
     * @var int<0, max>
     */
    private int $depth = 0;

    /**
     * The files read so far by their pathnames
     *
     * @var array<non-empty-string, FileInterface>
     */
    private array $read = [];

    /**
     * The pathnames of the files that output nothing
     *
     * @var array<non-empty-string, true>
     */
    private array $skipped = [];

    public function __construct(
        /**
         * Reads the files into the chunks
         */
        private readonly ChunkReader $reader,
        /**
         * The handlers of the directives
         */
        private readonly DirectiveRepositoryInterface $directives,
        /**
         * The macros defined at the moment, `#define` and `#undef` change them
         */
        private readonly MacroRepository $macros,
        /**
         * The files `#include` and `#embed` search
         */
        private readonly FileSystemInterface $files,
        /**
         * Receives the messages of `#error` and `#warning`
         */
        private readonly DiagnosticListenerInterface $diagnostic,
        /**
         * The number of the files that may be open at once, the first one included
         *
         * @var int<1, max>
         */
        private readonly int $maxDepth,
    ) {
        $this->expander = new MacroExpander($macros);
    }

    /**
     * Returns the files read so far, the first one and the included ones, each once.
     *
     * @return list<FileInterface>
     */
    public function getFiles(): array
    {
        return \array_values($this->read);
    }

    public function skip(FileInterface $file): void
    {
        $this->skipped[$file->pathname] = true;
    }

    private function createContext(): DirectiveContext
    {
        return new DirectiveContext(
            output: new OutputStack(),
            diagnostic: $this->diagnostic,
            macros: $this->macros,
            files: $this->files,
            executor: $this,
        );
    }

    /**
     * @return \Generator<int, TaggedChunk>
     * @throws IncludeDepthException in case of the files are nested deeper than the limit
     * @throws UnknownDirectiveException in case of a directive of a selected group has no handler
     * @throws MisplacedDirectiveException in case of a conditional section is not closed in the file
     * @throws UnclosedCommentException in case of the file ends in a block comment that is not closed
     * @throws UnmatchedTextException in case of the text of the file cannot be matched
     * @throws MacroInvocationException in case of a macro invocation in the text is malformed
     * @throws PreprocessorException in case of a directive cannot be executed
     * @throws SourceExceptionInterface in case of the file cannot be read
     * @throws \Throwable in case of the diagnostic listener stops the preprocessing
     */
    public function execute(FileInterface $file): \Generator
    {
        if (isset($this->skipped[$file->pathname])) {
            return;
        }

        $including = $this->current;

        if (++$this->depth > $this->maxDepth) {
            \assert($including !== null);

            throw IncludeDepthException::becauseLimitIsExceeded($including, $this->maxDepth);
        }

        $this->read[$file->pathname] ??= $file;

        try {
            $context = $this->createContext();
            $end = 0;

            foreach ($this->reader->read($file) as $chunk) {
                $end = $chunk->offset;

                if ($chunk instanceof DirectiveChunk) {
                    yield from $this->executeDirectiveChunk($chunk, $context);

                    continue;
                }

                if ($context->output->isEnabled()) {
                    yield $this->expandSourceChunk($chunk);
                }
            }

            if ($context->output->hasSection()) {
                throw MisplacedDirectiveException::becauseClosingIsMissing($file, $end, 'if', 'endif');
            }
        } finally {
            --$this->depth;
            $this->current = $including;
        }
    }

    /**
     * @return iterable<int, TaggedChunk>
     * @throws UnknownDirectiveException in case of the directive of a selected group has no handler
     * @throws PreprocessorException in case of the directive cannot be executed
     * @throws \Throwable in case of the diagnostic listener stops the preprocessing
     */
    private function executeDirectiveChunk(DirectiveChunk $directive, DirectiveContext $context): iterable
    {
        if ($directive->name === '') {
            return;
        }

        $handler = $this->directives->find($directive->name);
        $isEnabled = $context->output->isEnabled();

        if ($handler === null && $isEnabled) {
            throw UnknownDirectiveException::becauseHandlerIsMissing($directive);
        }

        if ($handler === null || !($isEnabled || $handler instanceof OutputIndependentDirectiveHandlerInterface)) {
            return;
        }

        $this->current = $directive;

        yield from $handler->handle($directive, $context);
    }

    /**
     * @throws MacroInvocationException in case of a macro invocation in the text is malformed
     */
    private function expandSourceChunk(TaggedChunk $chunk): TaggedChunk
    {
        $text = $this->expander->expand($chunk->content);

        if ($text === $chunk->content) {
            return $chunk;
        }

        return new GeneratedChunk($text, $chunk->source, $chunk->offset);
    }
}
