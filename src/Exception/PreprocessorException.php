<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Contracts\Source\ReadableInterface as Src;
use Phplrt\Exception\RuntimeException;

abstract class PreprocessorException extends RuntimeException
{
    /**
     * @return static
     */
    public static function fromSource(string $msg, Src $src, TokenInterface $tok, ?\Throwable $prev = null): self
    {
        $exception = new static($msg, 0, $prev);
        $exception->setSource($src);
        $exception->setToken($tok);

        return $exception;
    }
}
