<?php

declare(strict_types=1);

namespace FFI\Preprocessor;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\DiagnosticListener\RecordingDiagnosticListener;
use FFI\Preprocessor\Macro\MacroRepositoryInterface;
use FFI\Preprocessor\Runtime\Chunk\TaggedChunk;
use FFI\Preprocessor\Runtime\SourceExecutor;
use Phplrt\Contracts\Source\FileInterface;

/**
 * The outcome of one preprocessor run: the output and everything the run
 * learned on the way. The run goes on while the output is read, so the
 * output is read once and kept, and every getter reads the rest of it first.
 *
 * @template-implements \IteratorAggregate<int, TaggedChunk>
 */
final class Result implements
    \IteratorAggregate,
    \Stringable
{
    /**
     * Chunks of the output read so far
     *
     * @var list<TaggedChunk>
     */
    private array $chunks = [];

    /**
     * @var \Iterator<array-key, TaggedChunk>
     */
    private readonly \Iterator $stream;

    private bool $isFinished = false;

    /**
     * @param \Traversable<array-key, TaggedChunk> $stream
     */
    public function __construct(
        \Traversable $stream,
        private readonly MacroRepositoryInterface $macros,
        private readonly SourceExecutor $executor,
        private readonly RecordingDiagnosticListener $diagnostics,
    ) {
        $this->stream = self::streamToIterator($stream);
    }

    /**
     * @param \Traversable<array-key, TaggedChunk> $stream
     * @return \Iterator<array-key, TaggedChunk>
     */
    private static function streamToIterator(\Traversable $stream): \Iterator
    {
        if ($stream instanceof \Iterator) {
            return $stream;
        }

        return new \IteratorIterator($stream);
    }

    /**
     * Returns the macros defined when the run ended, those of the
     * configuration included.
     *
     * @throws \Throwable in case of the run fails
     */
    public function getMacros(): MacroRepositoryInterface
    {
        $this->finish();

        return $this->macros;
    }

    /**
     * Returns the files the run read, the first one and the included ones, each once.
     *
     * @return list<FileInterface>
     * @throws \Throwable in case of the run fails
     */
    public function getFiles(): array
    {
        $this->finish();

        return $this->executor->getFiles();
    }

    /**
     * Returns the diagnostics of the run.
     *
     * @return list<Diagnostic>
     * @throws \Throwable in case of the run fails
     */
    public function getDiagnostics(): array
    {
        $this->finish();

        return $this->diagnostics->getDiagnostics();
    }

    /**
     * @throws \Throwable in case of the run fails
     */
    public function getIterator(): \Traversable
    {
        for ($i = 0;; ++$i) {
            if (!isset($this->chunks[$i]) && !$this->advance()) {
                return;
            }

            yield $this->chunks[$i];
        }
    }

    /**
     * @throws \Throwable in case of the run fails
     */
    public function __toString(): string
    {
        $buffer = '';

        foreach ($this as $chunk) {
            $buffer .= $chunk->content;
        }

        return $buffer;
    }

    /**
     * Reads the next chunk of the output.
     *
     * @return bool {@see false} in case of the output is over
     * @throws \Throwable in case of the run fails
     */
    private function advance(): bool
    {
        if ($this->isFinished) {
            return false;
        }

        if ($this->chunks === []) {
            $this->stream->rewind();
        } else {
            $this->stream->next();
        }

        if (!$this->stream->valid()) {
            $this->isFinished = true;

            return false;
        }

        $this->chunks[] = $this->stream->current();

        return true;
    }

    /**
     * @throws \Throwable in case of the run fails
     */
    private function finish(): void
    {
        // reads the output to the end
        foreach ($this as $_);
    }
}
