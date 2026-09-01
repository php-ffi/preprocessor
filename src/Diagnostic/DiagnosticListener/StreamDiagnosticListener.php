<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\DiagnosticListenerInterface;
use FFI\Preprocessor\Exception\InvalidStreamException;

/**
 * Writes the diagnostics to a stream as soon as they are reported, one line
 * each, the way the compilers print them: `file.h:3:2: warning: text`.
 */
final class StreamDiagnosticListener implements DiagnosticListenerInterface
{
    /**
     * Stream of the standard error output
     */
    private const STDERR = 'php://stderr';

    /**
     * Stream of the standard output
     */
    private const STDOUT = 'php://stdout';

    /**
     * Modes of {@see \fopen()} that allow writing
     */
    private const WRITABLE_MODE_PATTERN = '/[waxc+]/';

    /**
     * The stream the diagnostics are written to
     *
     * @var resource
     */
    private readonly mixed $stream;

    /**
     * @param resource $stream a stream open for writing
     * @throws InvalidStreamException in case of the value is not a stream open for writing
     */
    public function __construct(mixed $stream)
    {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw InvalidStreamException::becauseValueIsNotStream($stream);
        }

        $meta = \stream_get_meta_data($stream);

        if (\preg_match(self::WRITABLE_MODE_PATTERN, $meta['mode']) !== 1) {
            throw InvalidStreamException::becauseStreamIsNotWritable($meta['uri'] ?? '');
        }

        $this->stream = $stream;
    }

    /**
     * Creates the listener that writes to the standard error output.
     *
     * @throws InvalidStreamException in case of the standard error output cannot be opened
     */
    public static function createDefault(): self
    {
        return self::createFromStderr();
    }

    /**
     * @throws InvalidStreamException in case of the standard error output cannot be opened
     */
    public static function createFromStderr(): self
    {
        return new self(self::open(self::STDERR));
    }

    /**
     * @throws InvalidStreamException in case of the standard output cannot be opened
     */
    public static function createFromStdout(): self
    {
        return new self(self::open(self::STDOUT));
    }

    /**
     * @param non-empty-string $uri
     * @return resource
     * @throws InvalidStreamException in case of the stream cannot be opened
     */
    private static function open(string $uri): mixed
    {
        $stream = @\fopen($uri, 'wb');

        if ($stream === false) {
            throw InvalidStreamException::becauseStreamCannotBeOpened($uri);
        }

        return $stream;
    }

    public function notify(Diagnostic $diagnostic): void
    {
        $position = $diagnostic->findPosition();

        $place = $position === null
            ? $diagnostic->source->pathname
            : \sprintf('%s:%d:%d', $diagnostic->source->pathname, $position->line, $position->column);

        \fwrite($this->stream, \sprintf("%s: %s: %s\n", $place, $diagnostic->severity->value, $diagnostic->message));
    }
}
