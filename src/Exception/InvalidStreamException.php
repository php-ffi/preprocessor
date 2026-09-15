<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

/**
 * Occurs when the stream a diagnostic listener writes to cannot be used.
 */
final class InvalidStreamException extends PreprocessorException
{
    public static function becauseValueIsNotStream(mixed $value): self
    {
        return new self(\sprintf('Expected a stream resource, %s given', \get_debug_type($value)));
    }

    public static function becauseStreamIsNotWritable(string $uri): self
    {
        return new self(\sprintf('The stream "%s" is not open for writing', \addcslashes($uri, '"')));
    }

    public static function becauseStreamCannotBeOpened(string $uri): self
    {
        return new self(\sprintf('The stream "%s" cannot be opened', \addcslashes($uri, '"')));
    }
}
