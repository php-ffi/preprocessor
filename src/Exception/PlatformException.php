<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

final class PlatformException extends PreprocessorException
{
    public static function becausePlatformErrorOccurs(string $message): self
    {
        return new self($message);
    }
}
