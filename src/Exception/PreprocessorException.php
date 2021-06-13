<?php

/**
 * This file is part of FFI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FFI\Preprocessor\Exception;

use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Contracts\Source\ReadableInterface as Src;
use Phplrt\Exception\RuntimeException;

abstract class PreprocessorException extends RuntimeException
{
    /**
     * @param string $msg
     * @param Src $src
     * @param TokenInterface $tok
     * @param \Throwable|null $prev
     * @return static
     */
    public static function fromSource(string $msg, Src $src, TokenInterface $tok, \Throwable $prev = null): self
    {
        $exception = new static($msg, 0, $prev);
        $exception->setSource($src);
        $exception->setToken($tok);

        return $exception;
    }
}
